const token = location.hash.slice(1);
if (token) {
  document.querySelector("#setup-token").value = token;
  history.replaceState(null, "", location.pathname);
}
