/* Information display: donation list with search, blood-type filter and sort. */
(()=>{
  const {h,TYPES,fmtType,fmtDate,getDonations}=BL;
  const list=document.getElementById("list"),count=document.getElementById("count"),types=document.getElementById("types");
  const state={q:"",type:"",sort:"donation_date:desc"};
  let seq=0,timer;

  [["","All"],...TYPES.map(t=>[t,fmtType(t)])].forEach(([v,label])=>
    types.append(h("button",{type:"button",class:"type"+(v?"":" type--all"),"data-type":v,"aria-pressed":String(v===state.type)},label)));
  types.addEventListener("click",e=>{
    const b=e.target.closest("button");if(!b)return;
    state.type=b.dataset.type;
    types.querySelectorAll("button").forEach(x=>x.setAttribute("aria-pressed",String(x===b)));
    load();
  });
  document.getElementById("q").addEventListener("input",e=>{clearTimeout(timer);timer=setTimeout(()=>{state.q=e.target.value.trim();load()},250)});
  document.getElementById("sort").addEventListener("change",e=>{state.sort=e.target.value;load()});

  function card(d){
    const wait=!d.synced_at;
    const gps=d.latitude==null?"No GPS captured":`GPS ${Number(d.latitude).toFixed(4)}, ${Number(d.longitude).toFixed(4)}`;
    return h("article",{class:"rec"+(wait?" rec--wait":"")},
      h("div",{class:"rec__type"},h("span",{class:"glyph"},fmtType(d.blood_type)),h("span",{class:"rec__units"},d.units+(d.units>1?" units":" unit"))),
      h("div",{class:"rec__body"},
        h("h3",{},d.donor_name),
        h("p",{},d.bank_name),
        h("p",{},d.drive_title||"Walk-in donation"),
        h("p",{},fmtDate(d.donation_date)+", "+gps),
        h("div",{class:"rec__foot"},
          h("span",{class:"badge "+(wait?"badge--wait":"badge--ok")},wait?"Waiting to send":"Synced"),
          h("div",{class:"rec__actions"},
            h("a",{class:"btn btn--sm",href:"donation-form.html?id="+d.donation_id,"aria-label":"Edit donation by "+d.donor_name},"Edit"),
            // F3 listens for this event and shows the delete confirmation
            h("button",{type:"button",class:"btn btn--sm btn--danger","aria-label":"Delete donation by "+d.donor_name,
              onclick:()=>document.dispatchEvent(new CustomEvent("donation:delete",{detail:d}))},"Delete")))));
  }
  const skeleton=()=>list.replaceChildren(...Array.from({length:3},()=>h("div",{class:"rec sk","aria-hidden":"true"})));

  async function load(){
    const my=++seq;skeleton();count.textContent="Loading";
    try{
      const rows=await getDonations(state);
      if(my!==seq)return;
      const units=rows.reduce((s,d)=>s+Number(d.units),0);
      count.textContent=`${rows.length} donation${rows.length===1?"":"s"}, ${units} unit${units===1?"":"s"}`;
      list.replaceChildren(...(rows.length?rows.map(card):[h("div",{class:"empty"},
        h("h2",{},"No donations match"),h("p",{},"Try another name or blood type, or clear the filters."),
        h("button",{type:"button",class:"btn",onclick:()=>location.reload()},"Clear filters"))]));
    }catch(err){
      if(my!==seq)return;
      count.textContent="Could not load";
      list.replaceChildren(h("div",{class:"empty"},h("h2",{},"Can't load donations"),
        h("p",{},"Check your connection, then try again."),h("button",{type:"button",class:"btn btn--primary",onclick:load},"Try again")));
    }
  }
  load();
})();
