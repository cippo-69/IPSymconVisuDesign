(function(){
/* Hell/Dunkel: body.dark nach der Schriftfarbe der Visu; Module rufen w5tTheme() bei jedem Neuzeichnen auf */
window.w5tTheme=function(){var b=document.body;if(!b)return;
try{var m=/rgba?[(]([0-9]+),[ ]*([0-9]+),[ ]*([0-9]+)/.exec(getComputedStyle(b).color||"");
var d=m?(0.2126*m[1]+0.7152*m[2]+0.0722*m[3])/255>0.5:(window.matchMedia&&matchMedia("(prefers-color-scheme: dark)").matches);
b.classList.toggle("dark",!!d);}catch(e){}};
window.w5tTheme();
/* aufgeklappte Abschnitte je Kachel merken (Script-Kacheln mit data-store) */
document.querySelectorAll(".w5t[data-store]").forEach(function(r){var k="w5t:"+r.getAttribute("data-store"),o={};
try{o=JSON.parse(localStorage.getItem(k)||"{}");}catch(e){}
r.querySelectorAll("details[data-k]").forEach(function(x){if(o[x.dataset.k])x.open=true;
x.addEventListener("toggle",function(){o[x.dataset.k]=x.open;try{localStorage.setItem(k,JSON.stringify(o));}catch(e){}});});});})();
