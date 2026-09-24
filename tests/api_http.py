import json,urllib.request,urllib.parse,http.cookiejar,re,os
BASE=os.environ.get('TEST_BASE_URL','https://nyx-off.dev/others/pathfinder/')
credentials=json.load(open(os.path.join(os.path.dirname(__file__),'../storage/test-credentials.json')))
count=0

def check(ok,name):
 global count
 assert ok,name
 count+=1
 print('PASS',name)

def client():return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def request(opener,path,method='GET',data=None,csrf=None):
 headers={}
 if data is not None:headers['Content-Type']='application/json';data=json.dumps(data).encode()
 if csrf:headers['X-CSRF-Token']=csrf
 try:
  with opener.open(urllib.request.Request(BASE+path,data=data,headers=headers,method=method)) as r:return r.status,r.read(),r.headers
 except urllib.error.HTTPError as r:return r.code,r.read(),r.headers

def login(u):
 op=client();_,body,_=request(op,'index.php');token=re.search(rb'name="csrf" value="([^"]+)"',body)[1].decode()
 with op.open(BASE+'index.php',urllib.parse.urlencode(dict(csrf=token,action='login',**u)).encode()) as r:body=r.read()
 csrf=re.search(rb'name="csrf-token" content="([^"]+)"',body)[1].decode();check(b'id="app"' in body,'login session');return op,csrf
op,csrf=login(credentials[0]);other,other_csrf=login(credentials[1]);anonymous=client()
check(request(anonymous,'api.php')[0]==401,'unauthenticated API denied')
check(request(op,'api.php?action=create','POST',{'name':'CSRF'})[0]==403,'CSRF denied')
status,body,_=request(op,'api.php?action=create','POST',{'name':'Test HTTP <script>alert(1)</script>','ancestry':'Humain','class':'Magicien','attributes':{'con':2,'int':4},'class_hp':6,'skills':[{'name':'Arcanes','attribute':'int','rank':1,'misc':0}]},csrf);check(status==201,'POST character');c=json.loads(body)['data'];cid=c['id']

def act(action,data):
 global c
 status,body,_=request(op,f'api.php?action={action}&id={cid}','PATCH',dict(revision=c['revision'],**data),csrf)
 assert status==200,(action,status,body)
 c=json.loads(body)['data'];return c
check(request(other,f'api.php?id={cid}')[0]==404,'GET foreign owner denied')
check(request(other,f'api.php?action=hp&id={cid}','PATCH',{'revision':c['revision'],'mode':'heal','amount':1},other_csrf)[0]==404,'PATCH foreign owner denied')
act('hp',{'mode':'damage','amount':3});check(c['hp']==13,'HTTP damage');act('hp',{'mode':'heal','amount':2});check(c['hp']==15,'HTTP healing')
act('entry',{'collection':'items','name':'Potion','data':{'type':'consommable','quantity':2,'bulk':0.1}});iid=c['items'][0]['id'];act('use_item',{'entry_id':iid,'heal':1});check(c['hp']==16 and c['items'][0]['data']['quantity']==1,'HTTP potion consumption')
act('currency',{'amount':50,'unit':'po','reason':'Quête'});act('currency',{'amount':-15,'unit':'po','reason':'Dépense'});check(c['copper']==3500,'HTTP currency')
act('entry',{'collection':'conditions','name':'Effrayé','data':{'value':2}});check(c['computed']['stats']['Arcanes']['total']==5,'HTTP condition affects skill')
act('entry',{'collection':'spells','name':'Sort test','data':{'spell_rank':1,'casting':'prepared'}});sid=c['spells'][0]['id'];act('entry',{'collection':'spell_slots','name':'Arcane 1','data':{'spell_rank':1,'current':2,'max':2}});slot=c['spell_slots'][0]['id'];act('cast',{'spell_id':sid,'slot_id':slot});check(c['spell_slots'][0]['data']['current']==1 and c['spells'][0]['data']['used'],'HTTP spell cast atomic')
act('level_up',{'choices':'Choix HTTP','boosts':[]});check(c['level']==2,'HTTP level up')
status,body,_=request(op,f'api.php?action=export&id={cid}');document=json.loads(body);check(document['format']=='pf2-character','download export unwrapped')
status,body,_=request(op,'api.php?action=import','POST',document,csrf);check(status==200,'HTTP export import roundtrip');new=json.loads(body)['data'];request(op,f'api.php?action=delete&id={new["id"]}','DELETE',{'revision':new['revision']},csrf)
check(request(op,f'api.php?action=hp&id={cid}','PATCH',{'revision':1,'mode':'heal','amount':1},csrf)[0]==409,'HTTP stale edit denied')
for path in ['.env','.git/config','storage/characters.sqlite','storage/backups/','app/bootstrap.php','database/migrations/001_initial.php','config/']:
 check(request(anonymous,path)[0]==403,'protected '+path)
check(request(anonymous,'missing-page')[0]==404,'404 route')
status,_,_=request(op,f'api.php?action=delete&id={cid}','DELETE',{'revision':c['revision']},csrf);check(status==200,'HTTP delete')
check(request(op,f'api.php?id={cid}')[0]==404,'deleted character missing')
print(count,'HTTP tests passed')
