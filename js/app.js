/* Shared helpers + data layer. Flip USE_MOCK to false once B3's API is live. */
window.BL=(()=>{
  const USE_MOCK=true, API="api/";
  const TYPES=["A+","A-","B+","B-","AB+","AB-","O+","O-"];
  const fmtType=t=>t.replace("-","\u2212");
  const fmtDate=d=>new Date(d+"T00:00:00").toLocaleDateString("en-PH",{month:"short",day:"numeric",year:"numeric"});
  const wait=ms=>new Promise(r=>setTimeout(r,ms));

  // h("div",{class:"x"},child,...) builds DOM nodes; text is always set as text, never HTML
  function h(tag,attrs={},...kids){
    const n=document.createElement(tag);
    for(const [k,v] of Object.entries(attrs)){
      if(v==null||v===false)continue;
      if(k.startsWith("on"))n.addEventListener(k.slice(2),v);
      else n.setAttribute(k,v===true?"":v);
    }
    n.append(...kids.flat().filter(x=>x!=null&&x!==false));
    return n;
  }

  // Online/offline chip. F2 calls BL.setPending(n) with the IndexedDB queue size.
  let pending=0;
  function paint(){
    const el=document.querySelector(".net");if(!el)return;
    const off=!navigator.onLine;
    el.dataset.state=off?"offline":"online";
    el.textContent=off?(pending?`Offline, ${pending} waiting`:"Offline"):(pending?`Sending ${pending}`:"Online");
  }
  addEventListener("online",paint);addEventListener("offline",paint);addEventListener("DOMContentLoaded",paint);
  const setPending=n=>{pending=n;paint()};

  // ---- mock data (same shape as the assumed API contract in README.md)
  const BANK="Cordillera Blood Center",SLU="SLU Campus Drive",BP="Burnham Park Drive";
  const MOCK_DONATIONS=[
    ["Maria Santos","O+",BANK,SLU,1,"2026-10-12",16.4023,120.5960,1],
    ["Jose Dela Cruz","A+",BANK,BP,1,"2026-10-11",16.4120,120.5930,1],
    ["Angela Reyes","O-",BANK,SLU,2,"2026-10-11",16.4023,120.5960,0],
    ["Mark Villanueva","B+",BANK,null,1,"2026-10-10",16.4090,120.5970,1],
    ["Joy Ramos","AB+",BANK,BP,1,"2026-10-09",16.4120,120.5930,1],
    ["Paolo Bautista","O+",BANK,BP,1,"2026-10-09",16.4118,120.5934,1],
    ["Kristine Lacson","A-",BANK,SLU,1,"2026-10-08",16.4023,120.5960,1],
    ["Dennis Aquino","O+",BANK,null,2,"2026-10-07",16.4101,120.5948,0],
    ["Hannah Cruz","B-",BANK,SLU,1,"2026-10-06",16.4023,120.5960,1],
    ["Rafael Mendoza","A+",BANK,BP,1,"2026-10-05",16.4120,120.5930,1],
    ["Liza Pascual","O+",BANK,SLU,1,"2026-10-04",16.4023,120.5960,1],
    ["Carlo Navarro","AB-",BANK,null,1,"2026-10-03",16.4087,120.5951,1],
    ["Bea Domingo","B+",BANK,BP,1,"2026-10-02",16.4120,120.5930,1],
    ["Ian Soriano","A+",BANK,SLU,1,"2026-10-01",16.4023,120.5960,1]
  ].map((r,i)=>({donation_id:i+1,client_uuid:"mock-"+i,donor_name:r[0],blood_type:r[1],bank_name:r[2],drive_title:r[3],units:r[4],donation_date:r[5],latitude:r[6],longitude:r[7],synced_at:r[8]?r[5]+" 18:00:00":null}));
  const MOCK_REQUESTS=[
    [1,"O-",4,"critical","2026-10-14"],[2,"A+",6,"urgent","2026-10-16"],
    [3,"B+",2,"routine","2026-10-20"],[4,"O+",3,"urgent","2026-10-15"],[5,"AB-",2,"routine","2026-10-22"]
  ].map(r=>({request_id:r[0],bank_name:BANK,blood_type:r[1],units_needed:r[2],urgency:r[3],status:"open",needed_by:r[4]}));

  async function get(file,params,mockData){
    if(USE_MOCK){await wait(350);return mockData}
    const r=await fetch(API+file+"?"+new URLSearchParams(params));
    if(!r.ok)throw new Error("HTTP "+r.status);
    const j=await r.json();
    if(!j.ok)throw new Error(j.error||"Request failed");
    return j.data;
  }
  async function getDonations({q="",type="",sort="donation_date:desc"}={}){
    let rows=await get("donations.php",{q,blood_type:type,sort},MOCK_DONATIONS);
    if(USE_MOCK){ // the real API does this in SQL
      const s=q.toLowerCase(),[f,dir]=sort.split(":");
      rows=rows.filter(d=>(!type||d.blood_type===type)&&(!s||[d.donor_name,d.bank_name,d.drive_title||""].join(" ").toLowerCase().includes(s)));
      rows=[...rows].sort((a,b)=>(a[f]>b[f]?1:a[f]<b[f]?-1:0)*(dir==="asc"?1:-1));
    }
    return rows;
  }
  const getRequests=()=>get("requests.php",{status:"open"},MOCK_REQUESTS);

  return {h,TYPES,fmtType,fmtDate,getDonations,getRequests,setPending};
})();
