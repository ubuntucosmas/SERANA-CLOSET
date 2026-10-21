// Replay server-generated test fixtures. All application requests are intercepted.
const { chromium } = await import(process.env.SERANA_PLAYWRIGHT_MODULE || 'playwright-core');
import fs from 'node:fs/promises';
import path from 'node:path';
const root=process.cwd();
const fixture=path.join(root,'storage/app/ux-preview');
const base='https://serana-closet.ddev.site';
const output=path.join(root,'docs/ux/verification');
await fs.mkdir(output,{recursive:true});
const homeHtml=await fs.readFile(path.join(fixture,'home.html'),'utf8');
const ziggy=homeHtml.match(/<script[^>]*>const Ziggy=[\s\S]*?<\/script>/)[0];
async function html(name){ return (await fs.readFile(path.join(fixture,name+'.html'),'utf8')).replace(/<script[^>]*>Object.assign\(Ziggy.routes,[\s\S]*?<\/script>/,()=>ziggy); }
const browser=await chromium.launch({executablePath:process.env.SERANA_CHROME_PATH || '/usr/bin/google-chrome-stable',headless:true,args:['--no-sandbox']});
const results=[];
const axePath=process.env.SERANA_AXE_PATH || path.join(root,'node_modules/axe-core/axe.min.js');
try {
const scenarios = process.env.SERANA_PREVIEW_SCENARIOS ? JSON.parse(process.env.SERANA_PREVIEW_SCENARIOS) : [[1440,'light'],[768,'light'],[375,'light'],[1440,'dark']];
for(const [width, theme] of scenarios) {
 const context=await browser.newContext({viewport:{width,height:950},ignoreHTTPSErrors:true});
 await context.addInitScript(value => { if (!localStorage.getItem('serana-theme-v2')) localStorage.setItem('serana-theme-v2', value); }, theme);
 let pendingSale=null;
 let staffWasReset=false;
 let stockPreview=null;
 let lastStockEntry=null;
 context.setDefaultTimeout(10000);
 await context.route('**/*',async route=>{
  const req=route.request(),url=new URL(req.url());
  if(url.hostname!==new URL(base).hostname){return route.continue();}
  if(url.pathname.startsWith('/build/')){
   const file=path.join(root,'public',url.pathname);
   try{return route.fulfill({path:file,contentType:file.endsWith('.js')?'application/javascript':'text/css'});}catch{return route.abort();}
  }
  if(url.pathname==='/studio'){
   const tab=url.searchParams.get('tab')||'home';
   const inertia=!!req.headers()['x-inertia'];
   if(inertia){const data=tab==='stock' && stockPreview ? structuredClone(stockPreview) : JSON.parse(await fs.readFile(path.join(fixture,staffWasReset && tab==='staff' ? 'staff-reset.json' : tab+'.json'),'utf8'));data.url=url.pathname+url.search;return route.fulfill({json:data,headers:{'X-Inertia':'true'}});}
   return route.fulfill({body:await html(tab),contentType:'text/html'});
  }
  if(/\/studio\/stock\/product\/\d+\/batch$/.test(url.pathname)){
   lastStockEntry=req.postDataJSON();
   stockPreview=stockPreview || JSON.parse(await fs.readFile(path.join(fixture,'stock.json'),'utf8'));
   const product=stockPreview.props.stock.products.find(p=>p.id===Number(url.pathname.split('/')[4]));
   for(const row of lastStockEntry.rows){const size=product.sizes.find(s=>s.id===row.variant_id);size.on_hand=lastStockEntry.action==='count'?Number(row.quantity):size.on_hand+(lastStockEntry.action==='damage'?-1:1)*Number(row.quantity);size.available=Math.max(0,size.on_hand-size.held);size.version++;}
   stockPreview.props.flash.success='Stock entry saved.';
   stockPreview.url='/studio?tab=stock';
   return route.fulfill({json:stockPreview,headers:{'X-Inertia':'true'}});
  }
  if(/\/studio\/staff\/\d+\/history$/.test(url.pathname))return route.fulfill({path:path.join(fixture,'staff-history.json'),contentType:'application/json'});
  if(/\/studio\/staff\/\d+\/reset-password$/.test(url.pathname)){
   staffWasReset=true;
   const data=JSON.parse(await fs.readFile(path.join(fixture,'staff-reset.json'),'utf8'));
   return route.fulfill({json:data,headers:{'X-Inertia':'true'}});
  }
  if(url.pathname.startsWith('/preview/'))return route.fulfill({body:await html(url.pathname.split('/').pop()),contentType:'text/html'});
  if(url.pathname==='/pos')return route.fulfill({body:await html('pos'),contentType:'text/html'});
  if(url.pathname==='/pos/catalogue' && process.env.SERANA_POS_LAYOUT_STRESS==='1'){
   const data=JSON.parse(await fs.readFile(path.join(fixture,'catalogue.json'),'utf8'));
   const original=data.products[0];
   data.products=Array.from({length:36},(_,i)=>({...original,id:original.id+i,name:i ? `Studio garment ${i+1} with a longer product name` : original.name,sizes:original.sizes.map(size=>({...size,id:size.id+i,sku:`PREVIEW-${i}`,available:30}))}));
   return route.fulfill({json:data});
  }
  const mapping={'/pos/catalogue':'catalogue','/pos/till/report':'report','/pos/sales':'sale','/pos/till/open':'opened','/pos/till/close':'closed','/pos/collect/find':'pickup'};
  if(mapping[url.pathname])return route.fulfill({path:path.join(fixture,mapping[url.pathname]+'.json'),contentType:'application/json'});
  if(/\/pos\/collect\/\d+/.test(url.pathname))return route.fulfill({path:path.join(fixture,'collected.json'),contentType:'application/json'});
  if(/\/pos\/sales\/\d+\/tender/.test(url.pathname)){
   if(req.postDataJSON()?.method==='mpesa_prompt'){
    pendingSale=JSON.parse(await fs.readFile(path.join(fixture,'sale.json'),'utf8'));
    pendingSale.sale.prompt={status:'pending',amount:5000};
    return route.fulfill({json:pendingSale});
   }
   return route.fulfill({path:path.join(fixture,'paid.json'),contentType:'application/json'});
  }
  if(/\/pos\/sales\/\d+$/.test(url.pathname))return route.fulfill({json:pendingSale||JSON.parse(await fs.readFile(path.join(fixture,'sale.json'),'utf8'))});
  return route.fulfill({json:{}});
 });
 const pages = process.env.SERANA_PREVIEW_PAGES?.split(',') || ['home','orders','bespoke','refunds','products','stock','staff','activity','settings','tills','pos','pos-closed','manager','cashier'];
 for(const tab of pages) {
  const page=await context.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto(base+(tab==='pos'?'/pos':['pos-closed','manager','cashier','website-cashier','website-client'].includes(tab)?'/preview/'+tab:'/studio?tab='+tab),{waitUntil:'networkidle'});
  if(tab==='pos' && process.env.SERANA_POS_THEME_CHECK==='1'){
   const before=await page.locator('.sales-desk').evaluate(el=>getComputedStyle(el).backgroundColor);
   await page.getByRole('button',{name:theme==='dark'?'Switch to Light Theme':'Switch to Dark Theme',exact:true}).click();
   await page.waitForTimeout(600); // Wait for the shared theme colour transition.
   const switched=await page.locator('.sales-desk').evaluate(el=>getComputedStyle(el).backgroundColor);
   if(before===switched)throw Error('Theme switch did not change the POS surface');
   await page.reload({waitUntil:'networkidle'});
   if(await page.evaluate(()=>localStorage.getItem('serana-theme-v2'))!==(theme==='dark'?'light':'dark'))throw Error('Theme choice did not persist');
   await page.getByRole('button',{name:theme==='dark'?'Switch to Dark Theme':'Switch to Light Theme',exact:true}).click();
   await page.waitForTimeout(600);
  }
  if(tab==='orders')await page.screenshot({path:path.join(output,`order-detail-${width}${theme==='dark'?'-dark':''}.png`),fullPage:true});
  if(tab==='orders')await page.getByRole('button',{name:'Close order preview'}).click();
  let accessibility=[];
  if(width===1440){await page.addScriptTag({path:axePath}); accessibility=await page.evaluate(async()=>{const result=await axe.run(document,{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21aa']}});return result.violations.map(v=>({id:v.id,impact:v.impact,nodes:v.nodes.length,examples:v.nodes.slice(0,2).map(n=>n.target)}));});}
  const overflow=await page.evaluate(()=>document.documentElement.scrollWidth-innerWidth);
  results.push({width,theme,tab,overflow,errors,accessibility}); console.log(JSON.stringify({width,tab,overflow,errors}));
  if(errors.length)throw Error(errors.join("; "));
  if(['home','orders','settings','pos','staff'].includes(tab))await page.screenshot({path:path.join(output,`${tab}-${width}${theme==='dark'?'-dark':''}.png`),fullPage:true});
  if(tab==='pos'){
   const website=page.getByRole('link',{name:'Website',exact:true});
   if(new URL(await website.getAttribute('href'),base).pathname!=='/')throw Error('Website link did not target the storefront');
   if(process.env.SERANA_POS_LAYOUT_STRESS==='1'){
    const products=page.locator('.desk-product-card');
    for(let i=0;i<12;i++)await products.nth(i).click();
    await page.screenshot({path:path.join(output,`pos-populated-${width}.png`),fullPage:true});
   }
   const layout=await page.evaluate(()=>{
    const workspace=document.querySelector('.desk-sale-workspace').getBoundingClientRect();
    const cart=document.querySelector('.desk-cart').getBoundingClientRect();
    const footer=document.querySelector('.desk-cart-footer').getBoundingClientRect();
    const body=document.querySelector('.desk-cart-body');
    return {workspaceWidth:workspace.width,cartTop:cart.top,workspaceTop:workspace.top,checkoutBottom:footer.bottom,viewportHeight:innerHeight,cartScrolls:body.scrollHeight>body.clientHeight};
   });
   results[results.length-1].pos_layout=layout;
   if(width>=768 && (layout.workspaceWidth<width*.9 || Math.abs(layout.cartTop-layout.workspaceTop)>1 || layout.checkoutBottom>layout.viewportHeight))throw Error('POS columns or checkout are outside the intended workspace');
   if(process.env.SERANA_POS_LAYOUT_STRESS==='1' && width>=768 && !layout.cartScrolls)throw Error('Long cart did not scroll independently');
   if(process.env.SERANA_POS_LAYOUT_STRESS==='1'){
    for(const label of ['Custom orders','Register','Order pickup']){
     await page.locator('nav button').filter({hasText:label}).click();
     if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth))throw Error(`${label} overflowed at ${width}px`);
     await page.screenshot({path:path.join(output,`pos-${label.toLowerCase().replaceAll(' ','-')}-${width}.png`),fullPage:true});
    }
   }
  }
  if(tab.startsWith('website-')){
   if(width<1024)await page.getByRole('button',{name:'Toggle menu',exact:true}).click();
   const pos=page.getByRole('link',{name:'Open POS',exact:true});
   if(tab==='website-cashier'){
    if(!await pos.first().isVisible())throw Error('Cashier cannot find POS from the website');
    if(new URL(await pos.first().getAttribute('href'),base).pathname!=='/pos')throw Error('Cashier POS link points to the wrong page');
   }else if(await pos.count())throw Error('Customer saw staff POS navigation');
   await page.screenshot({path:path.join(output,`${tab}-${width}-${theme}.png`),fullPage:true});
  }
  if(tab==='stock' && process.env.SERANA_STOCK_ENTRY_CHECK==='1'){
   await page.getByRole('button').filter({hasText:'Linen Two-Piece Set'}).first().click();
   await page.getByRole('button',{name:'Receive delivery',exact:true}).click();
   await page.getByRole('spinbutton',{name:'Top M quantity',exact:true}).fill('3');
   await page.getByRole('spinbutton',{name:'Bottom L quantity',exact:true}).fill('2');
   await page.getByRole('textbox',{name:'Reason',exact:true}).fill('Workshop batch');
   await page.getByRole('textbox',{name:'Delivery / document reference (optional)',exact:true}).fill('DELIVERY-PREVIEW');
   await page.getByRole('button',{name:'Review changes',exact:true}).click();
   await page.getByText('Review 2 size(s) above. Save all changes together.',{exact:true}).waitFor();
   await page.screenshot({path:path.join(output,`stock-entry-${width}-${theme}.png`),fullPage:true});
   if(width===1440){const findings=await page.evaluate(async()=>{const r=await axe.run(document,{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21aa']}});return r.violations.map(v=>({id:v.id,impact:v.impact,nodes:v.nodes.length,examples:v.nodes.slice(0,2).map(n=>n.target)}));});results[results.length-1].accessibility.push(...findings);}
   await page.getByRole('button',{name:'Save stock entry',exact:true}).click();
   await page.getByRole('form',{name:'Size quantities',exact:true}).waitFor({state:'hidden'});
   if(lastStockEntry?.rows.length!==2 || !lastStockEntry?.operation_id || lastStockEntry?.action!=='receive')throw Error('Delivery matrix sent incorrect rows or omitted its retry key');
   await page.getByRole('button',{name:'Count unsold stock',exact:true}).click();
   await page.getByRole('spinbutton',{name:'Top M quantity',exact:true}).fill('0');
   await page.getByRole('textbox',{name:'Reason',exact:true}).fill('Checked unsold shelf');
   const confirmation=page.getByRole('checkbox',{name:/I counted unsold stock only/});
   if(!await confirmation.evaluate(el=>el.required))throw Error('Paid pickup exclusion is not required');
   await confirmation.check();
   await page.getByRole('button',{name:'Review changes',exact:true}).click();
   await page.screenshot({path:path.join(output,`stock-count-${width}-${theme}.png`),fullPage:true});
   await page.getByRole('button',{name:'Save stock entry',exact:true}).click();
   await page.getByRole('form',{name:'Size quantities',exact:true}).waitFor({state:'hidden'});
   if(lastStockEntry?.action!=='count' || !lastStockEntry.exclude_paid_pickups || lastStockEntry.rows.length!==1 || Number(lastStockEntry.rows[0].quantity)!==0 || lastStockEntry.rows[0].expected_version===undefined)throw Error('Count did not preserve explicit zero, stock version or pickup exclusion');
   results[results.length-1].stock_entry='passed';
   await page.waitForTimeout(700);
   const overflow=await page.evaluate(()=>document.documentElement.scrollWidth-innerWidth);
   if(overflow>0){const offenders=await page.evaluate(()=>[...document.querySelectorAll('body *')].filter(el=>el.getBoundingClientRect().right>innerWidth).map(el=>({tag:el.tagName,classes:el.className,right:el.getBoundingClientRect().right,parent:el.parentElement?.className,overflow:getComputedStyle(el).overflowX})).slice(0,40));throw Error(`Stock matrix overflowed by ${overflow}px: ${JSON.stringify(offenders)}`);}
  }
  if(tab==='staff'){
   const card=page.getByRole('list',{name:'Staff',exact:true}).locator(':scope > li').filter({hasText:'Preview Cashier'});
   await card.getByRole('button',{name:'Edit details',exact:true}).click();
   await card.getByRole('textbox',{name:'Staff name',exact:true}).fill('Updated Preview Cashier');
   await card.getByRole('button',{name:'Cancel',exact:true}).click();
   await card.getByRole('button',{name:'End sessions',exact:true}).click();
   await card.getByRole('heading',{name:'End active sessions',exact:true}).waitFor();
   await card.getByRole('button',{name:'Cancel',exact:true}).click();
   await card.getByRole('button',{name:'Access history',exact:true}).click();
   await card.getByText('Staff account created',{exact:true}).waitFor();
   await page.screenshot({path:path.join(output,`staff-history-${width}-${theme}.png`),fullPage:true});
   await card.getByRole('button',{name:'Reset sign-in password',exact:true}).click();
   await card.getByLabel('New temporary password (optional)',{exact:false}).fill('preview-reset-4821');
   await card.getByLabel('Confirm new temporary password',{exact:true}).fill('preview-reset-4821');
   await card.getByRole('textbox',{name:'Reason for this change',exact:true}).fill('Forgot password');
   page.once('dialog',dialog=>dialog.accept());
   await card.getByRole('button',{name:'Issue temporary password',exact:true}).click();
   await page.locator('#staff-issued-credentials').getByText('preview-reset-4821',{exact:true}).waitFor();
   await card.getByText('still on the one-time password',{exact:false}).waitFor();
   await page.screenshot({path:path.join(output,`staff-reset-${width}-${theme}.png`),fullPage:true});
   results[results.length-1].staff_management='passed';
   if(width===1440){const findings=await page.evaluate(async()=>{const r=await axe.run(document,{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21aa']}});return r.violations.map(v=>({id:v.id,impact:v.impact,nodes:v.nodes.length,examples:v.nodes.slice(0,2).map(n=>n.target)}));});results[results.length-1].accessibility.push(...findings);}
  }
  if(tab==='cashier' && await page.getByRole('link',{name:'Studio',exact:true}).count())throw Error('Cashier saw Studio navigation');
  if(tab==='manager' && await page.getByRole('button',{name:'Staff',exact:true}).count())throw Error('Manager saw owner Staff navigation');
  if(tab==='pos'&&width===1440&&theme==='light'&&process.env.SERANA_POS_LAYOUT_STRESS!=='1'){
   await page.getByRole('button').filter({hasText:'Linen Studio Hoodie'}).click();
   await page.getByRole('button',{name:'Continue to payment',exact:true}).click();
   await page.getByText('Cash received',{exact:false}).locator('input').fill('6000');
   if(!(await page.getByText('Change preview:').textContent()).includes('1,000'))throw Error('Cash change preview incorrect');
   await page.screenshot({path:path.join(output,'cash-payment.png'),fullPage:true});
   await page.getByRole('button',{name:'Confirm cash payment',exact:true}).click();
   await page.getByRole('button',{name:'New sale',exact:true}).last().waitFor();
   await page.screenshot({path:path.join(output,'sale-complete.png'),fullPage:true});
   await page.getByRole('button',{name:'New sale',exact:true}).last().click();
   await page.locator('nav button').filter({hasText:'Register'}).click();
   await page.getByRole('spinbutton',{name:'Cash counted',exact:true}).fill('900');
   await page.getByText('Difference:',{exact:false}).last().waitFor();
   if(!await page.getByRole('textbox',{name:'Reason for any difference',exact:true}).evaluate(el=>el.required))throw Error('Cash difference did not require an explanation');
   await page.screenshot({path:path.join(output,'cash-difference.png'),fullPage:true});
   await page.getByRole('button',{name:'Order pickup',exact:true}).click();
   await page.getByRole('textbox',{name:'Order reference',exact:true}).fill('SRN-PREVIEW');
   await page.getByRole('textbox',{name:'Customer phone',exact:true}).fill('0712345678');
   await page.locator('form').filter({has:page.getByRole('textbox',{name:'Order reference',exact:true})}).getByRole('button').click();
   const handover=page.getByRole('button',{name:'Hand over order',exact:true});
   await handover.waitFor();
   await page.screenshot({path:path.join(output,'pickup.png'),fullPage:true});
   await handover.click();
   await page.getByText(/handed over\./).waitFor();
   await page.getByRole('button',{name:'New sale',exact:true}).click();
   await page.getByRole('button').filter({hasText:'Linen Studio Hoodie'}).click();
   await page.getByRole('button',{name:'Continue to payment',exact:true}).click();
   await page.getByRole('button',{name:'M-Pesa prompt',exact:true}).click();
   await page.getByRole('textbox',{name:'Customer’s M-Pesa number',exact:true}).fill('0712345678');
   await page.getByRole('button',{name:'Send M-Pesa prompt',exact:true}).click();
   await page.getByText('Waiting for the customer to enter their M-Pesa PIN…',{exact:true}).waitFor();
   if(!await page.getByRole('button',{name:'Send M-Pesa prompt',exact:true}).isDisabled())throw Error('Duplicate prompt was not disabled');
   await page.screenshot({path:path.join(output,'mpesa-waiting.png'),fullPage:true});
  }
  await page.close();
 }
 await context.close();
}
} finally { await browser.close(); }
await fs.writeFile(path.join(output,process.env.SERANA_PREVIEW_RESULTS || 'browser-results.json'),JSON.stringify(results,null,2));
const failures=results.filter(row=>row.overflow>0||row.errors.length||row.accessibility.some(item=>['serious','critical'].includes(item.impact)));
console.log(JSON.stringify({checks:results.length,failures}));
if(failures.length)throw Error('Browser checks failed; see browser-results.json');
