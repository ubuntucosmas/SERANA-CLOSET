// Browser-only fixtures: no real sales, payments, customers or accounts are mutated.
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || 'playwright-core');
import assert from 'node:assert/strict';
const browser=await chromium.launch({executablePath:process.env.CHROME_BIN || '/usr/bin/google-chrome-stable',headless:true,args:['--no-sandbox','--ignore-certificate-errors']});
const results=[];
try {
 const page=await browser.newPage({ignoreHTTPSErrors:true,reducedMotion:'reduce'});const errors=[];page.on('pageerror',e=>errors.push(e.message));
 const base=process.env.SERANA_BASE_URL || 'https://serana-closet.ddev.site';await page.goto(base,{waitUntil:'networkidle'});
 const shared=await page.evaluate(()=>JSON.parse(document.querySelector('[data-page]').getAttribute('data-page')));const html=await(await page.request.get(base)).text();
 const user={id:999001,name:'QA Manager',role:'owner',abilities:['studio.access','pos.sell','money.record','stock.manage','stock.cutover','audit.review','staff.manage','till.review','settings.payments','settings.policies']};
 let deskReads=0;let paid=false;let capturedCheckout=null;
 const handover={method:'pickup',label:'Collect from shop',location:'QA Shop',address:'Shop 4, Nairobi',next:'Ready for collection. Bring your reference and phone.'};
 const deskOrder=()=>({id:991,reference:'SRN-QA-991',customer:'QA Client',kind:'bespoke',garment:'Hoodie',total:8000,paid:paid?8000:3000,balance_due:paid?0:5000,deposit_required:4000,deposit_due:paid?0:1000,payment_status:paid?'paid':'deposit_paid',state:'open',fulfilment:'completed',can_collect:paid,collect_blocker:paid?null:'KSh 5,000 is still owed.',prompt:null,lines:[{description:'QA custom hoodie',quantity:1,amount:8000}],handover,receipt_url:null});
 const sampleOrder={id:992,full_name:'QA Swap Client',cipher:'SRN-QA-992',type:'standard',status:'Paid',is_paid:true,order_state:'open',price_quoted:8000,amount_paid:8000,balance_due:0,fulfilment_status:'pending',payment_method:'mpesa',resolution:'needs_resolution',stock_problem:{waiting:false,items:[{product_id:1,name:'Old set',quantity:1}]},ledger_rows:[],items_with_urls:[]};
 const fulfil=(r,component,props)=>{
   const url=new URL(r.request().url());const state={...shared,component,url:url.pathname+url.search,props:{...shared.props,auth:{user},...props}};
   if(r.request().headers()['x-inertia'])return r.fulfill({status:200,headers:{'Content-Type':'application/json','X-Inertia':'true'},body:JSON.stringify(state)});
   const encoded=JSON.stringify(state).replaceAll('&','&amp;').replaceAll('"','&quot;').replaceAll('<','&lt;');return r.fulfill({contentType:'text/html',body:html.replace(/data-page="[^"]*"/,`data-page="${encoded}"`)});
 };
 await page.route(/\/(pos|studio|checkout)(?:\/|\?|$)/,async r=>{
   const url=new URL(r.request().url());const path=url.pathname;
   const json=data=>r.fulfill({status:200,contentType:'application/json',body:JSON.stringify(data)});
   if(path==='/pos/catalogue')return json({products:[]});
   if(path==='/pos/orders/search')return json({has_more:false,orders:[{...deskOrder(),placed_at:'21 Oct 2026 11:00'}]});
   if(path==='/pos/orders/991'){deskReads++;return json({order:deskOrder()});}
   if(path==='/pos/sales/991')return json({sale:{...deskOrder(),paid_so_far:deskOrder().paid,paid,balance_due:deskOrder().balance_due}});
   if(path==='/pos/collect/991'){assert.equal(r.request().postDataJSON().phone,'0712345678');return json({order:{...deskOrder(),fulfilment:'collected'}});}
   if(path==='/studio/stock/product/2/options')return json({where:'QA Shop',sizes:[{id:21,part:'top',size:'M',available:2},{id:22,part:'top',size:'L',available:0},{id:23,part:'bottom',size:'M',available:2}]});
   if(path==='/checkout/process'){capturedCheckout=r.request().postDataJSON();return json({success:true,order_id:999,reference:'SRN-QA-PICKUP',amount:1000,items:[{id:1,name:'QA top',price:1000,quantity:1}],payment_method:'whatsapp',whatsapp_url:'https://wa.me/254700000000',handover});}
   if(r.request().method()!=='GET')throw Error('Unexpected mutation '+path);
   if(path==='/pos')return fulfil(r,'Pos/Till',{operator:{id:user.id,name:user.name,role:'owner',can_approve:true},shop:'QA Shop',tills:[{id:1,register:'Till 1',opened_at:'today'}],policy:{idle_minutes:10,discount_percent:10,discount_cap:500,variance_alert:200},garments:[]});
   if(path==='/checkout')return fulfil(r,'Checkout',{pickup_locations:[{id:1,name:'QA Shop',address:'Shop 4, Nairobi'}],payments:{mpesa:false,whatsapp:true,default:'whatsapp'}});
   if(path==='/studio') {
     const tab=url.searchParams.get('tab')||'home';return fulfil(r,'Admin/StudioDashboard',{active_tab:tab,orders:{data:[sampleOrder],page:1,last_page:1,total:1},focus_order:null,products:[{id:2,name:'QA replacement set',price:8000,in_stock:true,available_stock:4,garment_type:'set'}],stock_v2:true,stats:{},attention:{},workflow:{},categories:[],gallery:[],archived_gallery:[],archived_products:[],leads:[],drops:[],refunds:[],activity:{rows:[{id:1,at:'21 Oct 2026 11:00',actor:'QA Manager',action:'stock.count',subject:'QA top',reason:'Stock take',where:'QA Shop',changes:[],size:'M',quantity:2,url:null}],page:1,last_page:1,total:1,filters:{},actors:[{id:user.id,name:user.name}]}});
   }
   throw Error('Unexpected fixture path '+path);
 });
 // A custom deposit can be recovered without recreating the order.
 await page.goto(base+'/pos',{waitUntil:'networkidle'});await page.getByRole('button',{name:/Orders$/}).click();
 await page.getByLabel('Reference, customer name or full phone number').fill('QA Client');await page.getByRole('button',{name:'Find orders',exact:true}).click();await page.getByRole('button',{name:'Open order',exact:true}).click();
 assert.match(await page.locator('body').innerText(),/SRN-QA-991/);await page.getByRole('button',{name:/Use remaining deposit/}).click();assert.equal(await page.getByLabel('Amount',{exact:true}).first().inputValue(),'1000');
 await page.reload({waitUntil:'networkidle'});assert.ok(deskReads>=2);assert.match(await page.locator('body').innerText(),/SRN-QA-991/);
 paid=true;await page.getByRole('button',{name:'Check payment status',exact:true}).click();await page.getByLabel('Verify the phone number on the order').fill('0712345678');await page.getByRole('button',{name:'Hand it over',exact:true}).click();await page.getByText('SRN-QA-991 handed over.',{exact:true}).waitFor();results.push('find, recover custom balance, check payment and verified collection');
 // Grouped navigation, completion guidance, activity and offered sizes.
 await page.goto(base+'/studio?tab=activity',{waitUntil:'networkidle'});assert.ok(await page.getByRole('heading',{name:'Inventory',exact:true}).isVisible());assert.ok(await page.getByText('Stock take',{exact:true}).isVisible());await page.getByText('Who, when, where and completion',{exact:true}).click();assert.ok(await page.getByText('What confirms completion?',{exact:true}).isVisible());
 await page.setViewportSize({width:390,height:850});await page.getByRole('button',{name:/More$/}).click();const menu=page.getByRole('dialog');assert.ok(await menu.getByRole('heading',{name:'Money',exact:true}).isVisible());await page.keyboard.press('Escape');await page.waitForTimeout(300);assert.equal(await page.locator('dialog[open]').count(),0);results.push('grouped desktop/mobile navigation, purpose/completion and Escape');
 await page.setViewportSize({width:1440,height:900});await page.goto(base+'/studio?tab=orders',{waitUntil:'networkidle'});await page.getByRole('button',{name:'Open order SRN-QA-992',exact:true}).click();await page.getByLabel('Swap to',{exact:true}).selectOption('2');await page.getByLabel('Top size',{exact:true}).selectOption('M');await page.getByLabel('Bottom size',{exact:true}).selectOption('M');assert.ok(await page.getByLabel('Top size',{exact:true}).locator('option[value="L"]').evaluate(el=>el.disabled));await page.getByLabel('Reason for this decision',{exact:true}).fill('Customer agreed');assert.ok(await page.getByRole('button',{name:'Swap',exact:true}).isEnabled());results.push('offered top/bottom selectors and sold-out guard');
 // Pickup checkout omits address and records the selected shop.
 await page.goto(base);await page.evaluate(()=>{localStorage.removeItem('serana_checkout');localStorage.setItem('serana_bag',JSON.stringify([{id:1,line_key:'1',name:'QA top',price:1000,quantity:1,options:{top_size:'M'},available_stock:4}]));});await page.goto(base+'/checkout',{waitUntil:'networkidle'});
 await page.getByLabel('Collect from shop · no collection fee',{exact:true}).check();await page.getByLabel('Full Name',{exact:true}).fill('QA Buyer');await page.getByLabel('Email',{exact:true}).fill('qa@example.test');await page.locator('#checkout-phone').fill('0712345678');assert.equal(await page.locator('#checkout-address').count(),0);
 const submit=page.getByRole('button',{name:/Place order ·/i});await submit.last().click();await page.waitForTimeout(500);assert.equal(capturedCheckout.fulfilment_method,'pickup');assert.equal(Number(capturedCheckout.pickup_location_id),1);assert.match(await page.locator('body').innerText(),/Collect from shop/);results.push('pickup checkout without address and saved handover');
 for(const width of [390,1440]){await page.setViewportSize({width,height:900});assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),`overflow ${width}`);}
 for (const theme of ['light','dark']) for (const width of [390,1440]) {
 await page.setViewportSize({width,height:900});await page.evaluate(theme=>localStorage.setItem('serana-theme-v2',theme),theme);
 for (const path of ['/studio?tab=activity','/studio?tab=orders','/pos?order=991']) { await page.goto(base+path,{waitUntil:'networkidle'});assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),`overflow ${path} ${width} ${theme}`); }
}
results.push('Studio history/orders and POS: 390/1440px, both stored themes, no horizontal overflow');
assert.deepEqual(errors,[]);console.log(JSON.stringify({passed:results,pageErrors:errors}));
} finally {await browser.close();}
