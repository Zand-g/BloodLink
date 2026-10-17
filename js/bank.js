/* Second role (Blood Bank): stock by blood type vs. open requests. */
(async()=>{
  const {h,TYPES,fmtType,fmtDate,getDonations,getRequests}=BL;
  const stockEl=document.getElementById("stock"),reqEl=document.getElementById("requests");
  const rank={critical:0,urgent:1,routine:2},label={critical:"Critical",urgent:"Urgent",routine:"Routine"};
  try{
    const [don,reqs]=await Promise.all([getDonations(),getRequests()]);
    const stock=Object.fromEntries(TYPES.map(t=>[t,0]));
    don.forEach(d=>{if(d.status==="cleared")stock[d.blood_type]+=Number(d.units)});
    const open=reqs.filter(r=>r.status==="open").sort((a,b)=>rank[a.urgency]-rank[b.urgency]||a.needed_by.localeCompare(b.needed_by));
    const short=new Set(open.filter(r=>(stock[r.blood_type]||0)<r.units_needed).map(r=>r.blood_type));
    stockEl.replaceChildren(...TYPES.map(t=>h("div",{class:"tile"+(short.has(t)?" tile--low":"")},
      h("span",{class:"glyph"},fmtType(t)),h("span",{class:"tile__n"},String(stock[t])),
      h("span",{class:"tile__l"},short.has(t)?"units, short for a request":"units on hand"))));
    reqEl.replaceChildren(...(open.length?open.map(r=>{
      const have=stock[r.blood_type]||0,lack=have<r.units_needed;
      return h("article",{class:"rec"},
        h("div",{class:"rec__type"},h("span",{class:"glyph"},fmtType(r.blood_type)),h("span",{class:"rec__units"},r.units_needed+" needed")),
        h("div",{class:"rec__body"},
          h("h3",{},"Needed by "+fmtDate(r.needed_by)),
          h("div",{class:"meter"+(lack?" meter--short":""),role:"img","aria-label":`${have} of ${r.units_needed} units on hand`},
            h("span",{style:"--p:"+Math.min(1,have/r.units_needed)})),
          h("div",{class:"rec__foot"},
            h("span",{class:"badge badge--"+r.urgency},label[r.urgency]),
            h("p",{},lack?`${r.units_needed-have} short, ${have} on hand`:`Covered, ${have} on hand`))));
    }):[h("div",{class:"empty"},h("h2",{},"No open requests"),h("p",{},"New hospital requests will show up here."))]));
  }catch(e){
    reqEl.replaceChildren(h("div",{class:"empty"},h("h2",{},"Can't load stock"),h("p",{},"Check your connection, then reload."),
      h("button",{type:"button",class:"btn btn--primary",onclick:()=>location.reload()},"Reload")));
  }
})();
