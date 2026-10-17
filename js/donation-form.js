(()=>{
  const params=new URLSearchParams(location.search);
  const donationId=params.get("id");
  const form=document.getElementById("donation-form");
  const message=document.getElementById("form-message");
  const saveButton=document.getElementById("save-button");
  const byId=id=>document.getElementById(id);
  let options;

  function showError(text){
    message.hidden=false;
    message.classList.add("form-message--error");
    message.textContent=text;
  }

  function option(value,label){
    const item=document.createElement("option");
    item.value=value;
    item.textContent=label;
    return item;
  }

  function fillSelect(select,items,emptyLabel,labelFor){
    select.replaceChildren(option("",emptyLabel));
    for(const item of items)select.append(option(item.id,labelFor(item)));
  }

  function fillDrives(){
    const bankId=byId("bank-id").value;
    const drives=options.drives.filter(drive=>String(drive.bank_id)===bankId);
    fillSelect(byId("drive-id"),drives.map(d=>({id:d.drive_id,...d})),"Walk-in / no drive",
      drive=>`${drive.title} — ${drive.drive_date}`);
  }

  function fillRequests(currentRequestId=null){
    const requests=[...options.requests];
    if(currentRequestId&&!requests.some(request=>Number(request.request_id)===Number(currentRequestId))){
      requests.unshift({request_id:currentRequestId,blood_type:"Existing",units_needed:"",urgency:""});
    }
    fillSelect(byId("request-id"),requests.map(request=>({id:request.request_id,...request})),
      "No blood request",request=>request.blood_type==="Existing"
        ?`Existing request #${request.request_id}`
        :`#${request.request_id} · ${request.blood_type} · ${request.units_needed} unit(s) · ${request.urgency}`);
  }

  function setValue(id,value){
    byId(id).value=value==null?"":String(value);
  }

  function setManilaNow(){
    const now=new Intl.DateTimeFormat("sv-SE",{
      timeZone:"Asia/Manila",year:"numeric",month:"2-digit",day:"2-digit",
      hour:"2-digit",minute:"2-digit",hourCycle:"h23"
    }).format(new Date()).replace(" ","T");
    setValue("screened-at",now);
    setValue("collected-at",now);
  }

  async function getJson(url){
    const response=await fetch(url,{headers:{Accept:"application/json"}});
    const result=await response.json();
    if(!response.ok||!result.ok)throw new Error(result.message||`Request failed (${response.status})`);
    return result;
  }

  function renderDetails(donation){
    const details=byId("record-details");
    const values=[
      ["Donor",donation.donor_name],
      ["Blood bank",donation.bank_name],
      ["Drive",donation.drive_title||"Walk-in"],
      ["Collected",donation.collected_at],
      ["Screened",donation.screened_at]
    ];
    details.replaceChildren(...values.map(([label,value])=>{
      const row=document.createElement("p");
      const strong=document.createElement("strong");
      strong.textContent=`${label}: `;
      row.append(strong,document.createTextNode(value));
      return row;
    }));
    details.hidden=false;
  }

  async function initialize(){
    try{
      const [optionData,recordData]=await Promise.all([
        getJson("api/donation_form_options.php"),
        donationId?getJson(`api/donation_read.php?donation_id=${encodeURIComponent(donationId)}`):Promise.resolve(null)
      ]);
      options=optionData;

      if(donationId){
        const donation=recordData.data;
        byId("page-title").textContent="Edit donation";
        byId("page-lede").textContent="Update collection status, volume, request, or remarks.";
        const screeningSection=byId("screening-section");
        screeningSection.hidden=true;
        screeningSection.querySelectorAll("input, select, textarea").forEach(control=>control.disabled=true);
        byId("record-details").dataset.donationId=donation.donation_id;
        renderDetails(donation);
        fillRequests(donation.request_id);
        setValue("request-id",donation.request_id);
        setValue("volume",donation.volume_ml);
        setValue("status",donation.donation_status);
        setValue("remarks",donation.remarks);
        setValue("latitude",donation.latitude);
        setValue("longitude",donation.longitude);
        setValue("gps-accuracy",donation.gps_accuracy_m);
        setValue("collected-at",donation.collected_at.replace(" ","T").slice(0,16));
        byId("collected-at").disabled=true;
        byId("latitude").disabled=true;
        byId("longitude").disabled=true;
        byId("gps-accuracy").disabled=true;
        byId("request-id").disabled=false;
        saveButton.textContent="Save changes";
      }else{
        fillSelect(byId("donor-id"),options.donors.map(donor=>({id:donor.donor_id,...donor})),"Choose a donor",
          donor=>`${donor.name} (#${donor.donor_id})`);
        fillSelect(byId("bank-id"),options.banks.map(bank=>({id:bank.bank_id,...bank})),"Choose a blood bank",
          bank=>bank.name);
        fillDrives();
        fillRequests();
        setManilaNow();
      }
      byId("bank-id").addEventListener("change",fillDrives);
      form.hidden=false;
    }catch(error){
      showError(error.message||"Could not load the donation form.");
    }
  }

  function numberOrNull(id){
    const value=byId(id).value;
    return value===""?null:Number(value);
  }

  function uuid(){
    if(typeof crypto.randomUUID==="function")return crypto.randomUUID();
    const bytes=crypto.getRandomValues(new Uint8Array(16));
    bytes[6]=(bytes[6]&0x0f)|0x40;
    bytes[8]=(bytes[8]&0x3f)|0x80;
    const hex=[...bytes].map(byte=>byte.toString(16).padStart(2,"0")).join("");
    return `${hex.slice(0,8)}-${hex.slice(8,12)}-${hex.slice(12,16)}-${hex.slice(16,20)}-${hex.slice(20)}`;
  }

  form.addEventListener("submit",async event=>{
    event.preventDefault();
    message.hidden=true;
    if(!form.reportValidity())return;

    const latitude=numberOrNull("latitude");
    const longitude=numberOrNull("longitude");
    if(!donationId&&((latitude===null)!==(longitude===null))){
      showError("Enter both latitude and longitude, or leave both blank.");
      return;
    }

    const requestId=byId("request-id").value;
    const volume=Number(byId("volume").value);
    const remarks=byId("remarks").value.trim()||null;
    saveButton.disabled=true;
    saveButton.textContent="Saving…";
    try{
      let url,method,payload;
      if(donationId){
        url="api/donation_update.php";
        method="PUT";
        payload={
          donation_id:Number(donationId),
          volume_ml:volume,
          status:byId("status").value,
          remarks,
          request_id:requestId?Number(requestId):null
        };
      }else{
        url="api/donation_create.php";
        method="POST";
        payload={
          screening:{
            client_uuid:uuid(),
            donor_id:Number(byId("donor-id").value),
            bank_id:Number(byId("bank-id").value),
            drive_id:byId("drive-id").value?Number(byId("drive-id").value):null,
            weight_kg:numberOrNull("weight"),
            hemoglobin_g_l:numberOrNull("hemoglobin"),
            bp_systolic:numberOrNull("bp-systolic"),
            bp_diastolic:numberOrNull("bp-diastolic"),
            temperature_c:numberOrNull("temperature"),
            result:"eligible",
            deferral_reason:null,
            deferred_until:null,
            screened_at:byId("screened-at").value
          },
          donation:{
            client_uuid:uuid(),
            request_id:requestId?Number(requestId):null,
            volume_ml:volume,
            status:byId("status").value,
            remarks,
            latitude,
            longitude,
            gps_accuracy_m:numberOrNull("gps-accuracy"),
            collected_at:byId("collected-at").value
          }
        };
      }
      const response=await fetch(url,{
        method,
        headers:{"Content-Type":"application/json",Accept:"application/json"},
        body:JSON.stringify(payload)
      });
      const result=await response.json();
      if(!response.ok||!result.ok)throw new Error(result.result?.message||result.message||"Could not save donation.");
      message.classList.remove("form-message--error");
      message.hidden=false;
      const savedMessage=donationId
        ?"Donation updated."
        :(result.result.message||"Donation saved.");
      message.replaceChildren(document.createTextNode(savedMessage+" "));
      const link=document.createElement("a");
      link.href="index.html";
      link.textContent="Return to donations";
      message.append(link);
      form.hidden=true;
    }catch(error){
      showError(error.message||"Could not save donation.");
    }finally{
      saveButton.disabled=false;
      saveButton.textContent=donationId?"Save changes":"Save donation";
    }
  });

  initialize();
})();
