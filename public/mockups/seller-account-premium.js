/* Throwaway seller-account design preview. Synthetic data; no requests or persistence. */
const icons = {
    overview:'<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
    orders:'<path d="M7 3h10l3 4v14H4V7zM4 7h16M9 11h6"/>',
    rx:'<path d="M7 21V3h6a4 4 0 0 1 0 8H7m5 0 8 10m-8 0 8-10"/>',
    returns:'<path d="M3 10h11a6 6 0 0 1 0 12M3 10l5-5m-5 5 5 5" transform="translate(0 -2)"/>',
    products:'<path d="m12 3 9 5v8l-9 5-9-5V8zm-9 5 9 5 9-5m-9 5v8M7.5 5.5l9 5"/>',
    stock:'<path d="M4 6h16M4 12h16M4 18h10"/><circle cx="18" cy="18" r="2"/>',
    tag:'<path d="M3 3h9l9 9-9 9-9-9z"/><circle cx="7.5" cy="7.5" r="1"/>',
    messages:'<path d="M21 14a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/>',
    star:'<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9z"/>',
    branches:'<path d="M4 10v11h16V10M3 10l2-7h14l2 7M3 10a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0M9 21v-6h6v6"/>',
    staff:'<circle cx="9" cy="8" r="3"/><path d="M3 21v-2a6 6 0 0 1 12 0v2m1-16a3 3 0 0 1 0 6m2 4a5 5 0 0 1 3 4v2"/>',
    settings:'<path d="m10 3-1 3-3 1-3 3 2 3-1 3 3 3 3-1 3 2 3-2 3 1 2-3-1-3 2-3-2-3-3-1-1-3z" transform="translate(0 0) scale(.96)"/><circle cx="12" cy="12" r="3"/>',
    clock:'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    user:'<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    chevrons:'<path d="m9 8 3-3 3 3m-6 8 3 3 3-3"/>',
    chevron:'<path d="m9 6 6 6-6 6"/>',
    arrow:'<path d="M4 12h16m-6-6 6 6-6 6"/>',
    back:'<path d="M20 12H4m6-6-6 6 6 6"/>',
    external:'<path d="M14 3h7v7m0-7L10 14M10 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-5"/>',
    bell:'<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
    menu:'<path d="M4 6h16M4 12h16M4 18h16"/>',
    close:'<path d="m6 6 12 12M18 6 6 18"/>',
    plus:'<path d="M12 5v14M5 12h14"/>',
    search:'<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',
    calendar:'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 11h18"/>',
    up:'<path d="m5 15 6-6 4 4 6-8m-6 0h6v6"/>',
    check:'<path d="m5 12 4 4L19 6"/>',
    capsule:'<path d="m8.5 19.5 11-11a5 5 0 0 0-7-7l-11 11a5 5 0 0 0 7 7z" transform="translate(1 1)"/><path d="m8 8 8 8"/>',
    bottle:'<path d="M9 3h6v4l3 3v11H6V10l3-3zM6 12h12M9 3V1h6v2"/>',
    upload:'<path d="M12 16V3m-5 5 5-5 5 5M4 15v6h16v-6"/>',
    send:'<path d="m22 2-7 20-4-9-9-4zm-11 11L22 2"/>',
    info:'<circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10v.1"/>',
    download:'<path d="M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5"/>',
};
const icon = name => `<svg viewBox="0 0 24 24" aria-hidden="true">${icons[name] || icons.overview}</svg>`;
const escapeHtml = value => String(value).replace(/[&<>"']/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[character]));
const money = cents => new Intl.NumberFormat('en-PH',{style:'currency',currency:'PHP'}).format(cents / 100);
const badge = (text, tone='') => `<span class="badge ${tone}"><i></i>${escapeHtml(text)}</span>`;
const statusBadge = status => badge(status, ({Pending:'amber',Preparing:'blue','On the way':'blue',Delivered:'green',Published:'green','Under review':'amber',Draft:'',Suspended:'red',Approved:'green',Requested:'amber',Resolved:'green',Closed:'',Active:'green',Invited:'amber'})[status] || '');
const button = (text, action, style='primary', glyph='') => `<button class="${style}" ${action}>${glyph ? icon(glyph) : ''}${text}</button>`;
const go = (text, page, style='primary', glyph='') => button(text, `data-go="${page}"`, style, glyph);
const heading = (title, note='', actions='') => `<div class="page-heading"><div class="heading-copy"><h1>${title}</h1>${note ? `<span class="heading-note">${note}</span>` : ''}</div><div class="page-actions">${actions}</div></div>`;
const field = (label, value='', type='text', extra='') => `<label class="field">${label}<input type="${type}" value="${escapeHtml(value)}" ${extra}></label>`;
const selectField = (label, options) => `<label class="field">${label}<select>${options.map(option=>`<option>${option}</option>`).join('')}</select></label>`;
const search = (placeholder, id='table-search') => `<label class="search-field">${icon('search')}<span class="sr-only">${placeholder}</span><input id="${id}" type="search" placeholder="${placeholder}" autocomplete="off"></label>`;
const table = (headers, rows, label) => `<div class="table-wrap" role="region" aria-label="${label}" tabindex="0"><table><thead><tr>${headers.map(header=>`<th scope="col">${header}</th>`).join('')}</tr></thead><tbody>${rows}</tbody></table></div>`;
const emptyRow = columns => `<tr><td colspan="${columns}" class="empty"><strong>No matching results</strong><p>Try another search or clear your filters.</p><button data-action="clear-filters">Clear filters</button></td></tr>`;
const tabs = items => `<div class="tabs" role="tablist" aria-label="Filter by status">${items.map(([label,count])=>`<button class="tab" role="tab" aria-selected="${state.tab===label}" tabindex="${state.tab===label ? 0 : -1}" data-tab="${label}">${label}${count!==undefined?` <span>${count}</span>`:''}</button>`).join('')}</div>`;
const initials = name => name.split(' ').map(word=>word[0]).slice(0,2).join('');
const branches = [
    {name:'Cebu Capitol',address:'Osmeña Boulevard, Cebu City',manager:'Andrea Santos',staff:8,orders:64,sales:2748000,status:'Open',hours:'8:00 AM – 9:00 PM'},
    {name:'IT Park',address:'Salinas Drive, Lahug, Cebu City',manager:'Miguel Cruz',staff:6,orders:48,sales:2016000,status:'Open',hours:'9:00 AM – 10:00 PM'},
    {name:'Mandaue',address:'A. S. Fortuna Street, Mandaue City',manager:'Carla Lim',staff:5,orders:36,sales:1500000,status:'Open',hours:'8:00 AM – 8:00 PM'},
];
const orders = [
    {id:1048,name:'Maria Santos',branch:'Cebu Capitol',items:3,total:68500,status:'Pending',time:'9:42 AM',rx:true},
    {id:1047,name:'Jose Villanueva',branch:'IT Park',items:2,total:29500,status:'Pending',time:'9:28 AM',rx:false},
    {id:1046,name:'Ana Dela Cruz',branch:'Cebu Capitol',items:4,total:124000,status:'Preparing',time:'9:15 AM',rx:false},
    {id:1045,name:'Paolo Garcia',branch:'Mandaue',items:2,total:46500,status:'On the way',time:'8:56 AM',rx:false},
    {id:1044,name:'Bea Mendoza',branch:'IT Park',items:3,total:42500,status:'Pending',time:'8:40 AM',rx:true},
    {id:1043,name:'Luis Ramos',branch:'Cebu Capitol',items:1,total:18000,status:'Delivered',time:'8:21 AM',rx:false},
    {id:1042,name:'Sofia Lim',branch:'Mandaue',items:2,total:37000,status:'Pending',time:'8:08 AM',rx:true},
    {id:1041,name:'Daniel Tan',branch:'IT Park',items:5,total:87000,status:'Delivered',time:'Yesterday',rx:false},
];
const products = [
    {id:1,name:'Paracetamol 500 mg',sku:'PAR-500-100',category:'Over-the-counter',price:350,stock:240,status:'Published',icon:'capsule',tone:'',rx:false},
    {id:2,name:'Vitamin C 500 mg',sku:'VIT-C-500',category:'Vitamins & supplements',price:850,stock:186,status:'Published',icon:'bottle',tone:'violet',rx:false},
    {id:3,name:'Amoxicillin 500 mg',sku:'AMX-500-30',category:'Prescription medicines',price:1500,stock:89,status:'Published',icon:'capsule',tone:'green',rx:true},
    {id:4,name:'Cetirizine 10 mg',sku:'CET-10-100',category:'Over-the-counter',price:1200,stock:8,status:'Published',icon:'capsule',tone:'violet',rx:false},
    {id:5,name:'Isopropyl alcohol 70% · 500 mL',sku:'ALC-70-500',category:'First aid',price:9500,stock:30,status:'Published',icon:'bottle',tone:'green',rx:false},
    {id:6,name:'Salbutamol 2 mg/5 mL',sku:'SAL-SYR-60',category:'Prescription medicines',price:11000,stock:0,status:'Published',icon:'bottle',tone:'',rx:true},
    {id:7,name:'Digital thermometer',sku:'THM-DIG-01',category:'Health devices',price:18500,stock:24,status:'Draft',icon:'products',tone:'',rx:false},
    {id:8,name:'Zinc + Vitamin C',sku:'ZNC-C-30',category:'Vitamins & supplements',price:16000,stock:42,status:'Under review',icon:'bottle',tone:'violet',rx:false},
];
const orderProductLines = [
    [[2,20],[1,30],[4,1]], [[0,30],[4,2]], [[0,10],[1,20],[4,3],[3,10]],
    [[1,10],[4,4]], [[2,10],[1,10],[4,2]], [[3,15]], [[0,20],[2,20]],
    [[1,40],[4,2],[0,20],[3,10],[2,10]],
];
orders.forEach((order,index)=>{
    order.lines=orderProductLines[index].map(([productIndex,quantity])=>({product:{...products[productIndex]},quantity}));
    order.items=order.lines.length;
    order.total=order.lines.reduce((total,line)=>total+line.product.price*line.quantity,0);
});
const staff = [
    {name:'Franil Reyes',email:'franil@example.test',role:'Owner',branch:'Head office',license:'—',status:'Active'},
    {name:'Andrea Santos',email:'andrea@example.test',role:'Manager',branch:'Cebu Capitol',license:'—',status:'Active'},
    {name:'Miguel Cruz',email:'miguel@example.test',role:'Manager',branch:'IT Park',license:'—',status:'Active'},
    {name:'Carla Lim',email:'carla@example.test',role:'Manager',branch:'Mandaue',license:'—',status:'Active'},
    {name:'Isabel Flores',email:'isabel@example.test',role:'Pharmacist',branch:'Cebu Capitol',license:'Valid · Aug 2027',status:'Active'},
    {name:'Rafael Lee',email:'rafael@example.test',role:'Pharmacist',branch:'IT Park',license:'Valid · Jun 2027',status:'Active'},
    {name:'Nina Torres',email:'nina@example.test',role:'Staff',branch:'Mandaue',license:'—',status:'Invited'},
];
const conversations = [
    {name:'Maria Santos',preview:'Is my order ready for delivery?',time:'9:45',messages:[['Is my order ready for delivery?','9:45 AM'],['Hello Maria! We’re checking your prescription. We’ll update you here once your order is confirmed.','9:47 AM',true]]},
    {name:'Jose Villanueva',preview:'Thank you for the update!',time:'9:32',messages:[['Can I confirm the delivery address?','9:30 AM'],['Of course. We have your address in Lahug, Cebu City.','9:31 AM',true],['Thank you for the update!','9:32 AM']]},
    {name:'Bea Mendoza',preview:'I sent a clearer photo.',time:'8:46',messages:[['I sent a clearer photo.','8:46 AM']]},
    {name:'Sofia Lim',preview:'Thank you for checking my order.',time:'8:12',messages:[['Thank you for checking my order.','8:12 AM']]},
];
const state = {page:'overview',tab:'All',query:'',scope:'all',category:'All categories',conversation:0,period:'30 days',variant:new URLSearchParams(location.search).get('variant') || 'studio'};
const roleProfiles = {
    owner:{name:'Franil Reyes',label:'Owner',email:'franil@example.test'},
    manager:{name:'Andrea Santos',label:'Branch manager',email:'andrea@example.test'},
    pharmacist:{name:'Isabel Flores',label:'Pharmacist',email:'isabel@example.test'},
    staff:{name:'Nina Torres',label:'Staff',email:'nina@example.test'},
};
const previewParameters = new URLSearchParams(location.search);
state.role=Object.hasOwn(roleProfiles,previewParameters.get('role'))?previewParameters.get('role'):'owner';
state.scope=state.role==='owner'?'all':state.role==='manager'&&previewParameters.get('scope')==='all'?'all':state.role==='staff'?'Mandaue':'Cebu Capitol';
state.licensed=state.role==='pharmacist'||(['owner','manager'].includes(state.role)&&previewParameters.get('licensed')==='1');
const access = () => {
    const canManage=['owner','manager'].includes(state.role);
    const headOffice=state.role==='owner'||(state.role==='manager'&&state.scope==='all');
    return {canManage,headOffice,canEditProducts:canManage&&headOffice,canReview:state.licensed};
};
const main = document.querySelector('main');
const dialog = document.getElementById('detail-dialog');
let returnFocus = null;
let toastTimer;
const scoped = records => state.scope==='all' ? records : records.filter(record=>record.branch===state.scope);
const chosenBranches = () => state.scope==='all' ? branches : branches.filter(branch=>branch.name===state.scope);
const productCell = product => `<span class="product-cell"><span class="product-thumb ${product.tone}">${icon(product.icon)}</span><span><strong>${escapeHtml(product.name)}</strong><small>${escapeHtml(product.sku)}</small></span></span>`;
const navGroups = [
    [null,[['overview','Overview','overview']]],
    ['Manage',[['orders','Orders','orders','4'],['prescriptions','Prescriptions','rx','3'],['returns','Returns','returns']]],
    ['Catalog',[['products','Products','products'],['stock','Branch stock','stock'],['aliases','Other names','tag']]],
    ['Customers',[['messages','Messages','messages','3'],['reviews','Reviews','star']]],
    ['Business',[['branches','Branches','branches'],['staff','Staff','staff'],['hours','Opening hours','clock'],['settings','Store settings','settings']]],
];
function renderNav(){
    const active = state.page.split('/')[0];
    const permissions=access();
    document.getElementById('navigation').innerHTML=navGroups.map(([label,items])=>{
        const visible=items.filter(([id])=>canOpenPage(id));
        if(!visible.length){return '';}
        return `<div class="nav-group">${label?`<span class="nav-label">${label}</span>`:''}${visible.map(([id,name,glyph,count])=>{
            const viewOnly=(id==='products'&&!permissions.canEditProducts)||(id==='returns'&&!permissions.canManage);
            const text=id==='settings'&&!permissions.headOffice?'Branch details':name;
            return `<a class="nav-link" href="#${id}" ${active===id?'aria-current="page"':''}>${icon(glyph)}<span>${text}</span>${viewOnly?'<small class="view-only-note">View only</small>':count?`<span class="nav-count">${id==='orders'?scoped(orders).filter(order=>order.status==='Pending').length:id==='prescriptions'?scoped(orders).filter(order=>order.rx).length:visibleConversationIds().length}</span>`:''}</a>`;
        }).join('')}</div>`;
    }).join('');
    document.getElementById('account-link').toggleAttribute('aria-current',active==='account');
    if(active==='account'){document.getElementById('account-link').setAttribute('aria-current','page');}
}
function orderRows(records, concise=false){
    return records.map(order=>`<tr><td><a class="table-link" href="#orders/${order.id}">JM-${order.id}</a>${concise?'':`<small>19 Nov · ${order.time}</small>`}</td><td><strong>${order.name}</strong>${concise?'':`<small>${order.items} items · ${order.branch}</small>`}</td>${concise?'':`<td>${order.branch}</td>`}<td>${statusBadge(order.status)}</td><td class="align-right money">${money(order.total)}</td><td class="align-right"><a href="#orders/${order.id}" class="row-arrow" aria-label="View order JM-${order.id}">${icon('chevron')}</a></td></tr>`).join('');
}
function salesChart(chartWidth=650,chartHeight=190){
    const weights=[27,38,32,46,34,58,43,36,62,48,54,70,50,65,78,59,73,61,84,69,91,80,73,88,100,83,104,93,117,102];
    const days=state.period==='Today'?1:state.period==='7 days'?7:30;
    const total=Math.round(chosenBranches().reduce((sum,branch)=>sum+branch.sales,0)*(days===1?.04:days===7?.24:1));
    const selected=weights.slice(-days);
    const weightTotal=selected.reduce((sum,value)=>sum+value,0);
    const values=selected.map(weight=>Math.round(total*weight/weightTotal));
    values[values.length-1]+=total-values.reduce((sum,value)=>sum+value,0);
    const axisStep=Math.ceil(Math.max(...values)/3/10000)*10000;
    const axisMax=axisStep*3;
    const label=cents=>cents===0?'\u20b10':`\u20b1${(cents/100000).toFixed(1).replace('.0','')}k`;
    const plotLeft=44,plotRight=chartWidth-8,plotWidth=plotRight-plotLeft;
    const baseline=chartHeight-34,plotHeight=chartHeight-58;
    const xLabels=days===1?[[plotLeft+plotWidth/2-16,'19 Nov']]:days===7?[[plotLeft,'13 Nov'],[plotLeft+plotWidth/2-16,'16 Nov'],[plotRight-30,'19 Nov']]:[[plotLeft,'21 Oct'],[plotLeft+plotWidth/3-12,'31 Oct'],[plotLeft+plotWidth*2/3-12,'10 Nov'],[plotRight-30,'19 Nov']];
    return `<svg class="chart" viewBox="0 0 ${chartWidth} ${chartHeight}" role="img" aria-label="Illustrative daily sales. ${days} days, total ${money(total)}.">${[0,1,2,3].map(index=>{const y=24+plotHeight/3*index;return `<path class="grid" d="M${plotLeft} ${y}H${plotRight}"/><text x="0" y="${y+3}">${label(axisStep*(3-index))}</text>`;}).join('')}${values.map((value,index)=>{const height=value/axisMax*plotHeight;const slot=plotWidth/days;const width=Math.min(days===1?32:14,slot*.55);return `<rect class="bar ${index>=days-5?'recent':''}" x="${plotLeft+slot*index+(slot-width)/2}" y="${baseline-height}" width="${width}" height="${height}" rx="2"><title>Day ${index+1}: ${money(value)}</title></rect>`;}).join('')}${xLabels.map(([x,text])=>`<text x="${x}" y="${chartHeight-8}">${text}</text>`).join('')}</svg>`;
}
function resizeChart(){
    const chart=main.querySelector('.chart');
    if(chart){const bounds=chart.getBoundingClientRect();chart.outerHTML=salesChart(Math.round(bounds.width),Math.round(bounds.height));}
}
function overview(){
    const selected = chosenBranches();
    const sales=selected.reduce((sum,branch)=>sum+branch.sales,0);
    const orderCount=selected.reduce((sum,branch)=>sum+branch.orders,0);
    const factor=state.period==='7 days'?.24:state.period==='Today'?.04:1;
    const scopeNote=state.scope==='all'?'All branches':state.scope;
    return `${heading('Overview','',button(`${icon('calendar')} 21 Oct – 19 Nov, 2026`,'data-action="period"','date-button'))}
    <section class="stats" aria-label="Sales summary"><div class="stat"><div class="stat-label">Sales ${icon('info')}</div><div class="stat-value">${money(Math.round(sales*factor))}</div><div class="stat-foot">${scopeNote} · ${state.period==='30 days'?'Last 30 days':state.period}</div></div><div class="stat"><div class="stat-label">Orders</div><div class="stat-value">${Math.round(orderCount*factor)}</div><div class="stat-foot">Excludes cancelled orders</div></div><div class="stat"><div class="stat-label">Published products</div><div class="stat-value">6</div><div class="stat-foot">1 draft <span class="muted">·</span> 1 under review</div></div><div class="stat"><div class="stat-label">Unique buyers</div><div class="stat-value">${Math.round((state.scope==='all'?112:40)*factor)}</div><div class="stat-foot">Across completed purchases</div></div></section>
    <div class="overview-grid"><section class="panel sales-panel"><div class="sales-title"><h2>Sales overview</h2><span class="legend"><i></i> Daily sales</span></div><p class="chart-period">${state.period==='30 days'?'Last 30 days':state.period==='7 days'?'Last 7 days':'Today'}</p>${salesChart()}</section>
    <section class="panel attention"><div class="section-head"><h2>Needs attention</h2><span class="attention-count">3 tasks</span></div><button class="task" data-go="orders" data-preset="Pending"><span class="task-count">${scoped(orders).filter(order=>order.status==='Pending').length}</span><span><strong>Orders to confirm</strong></span>${icon('chevron')}</button><button class="task" data-go="prescriptions"><span class="task-count">${scoped(orders).filter(order=>order.rx).length}</span><span><strong>Prescriptions to review</strong></span>${icon('chevron')}</button><button class="task" data-go="stock" data-preset="Low stock"><span class="task-count neutral">2</span><span><strong>Products to restock</strong><small>1 low stock · 1 sold out</small></span>${icon('chevron')}</button></section></div>
    <div class="overview-lower"><section class="panel"><div class="section-head table-panel-head"><h2>Recent orders</h2>${go('View all orders','orders','quiet','arrow')}</div>${table(['Order','Customer','Status','<span class="sr-only">Total</span>','<span class="sr-only">Details</span>'],orderRows(scoped(orders).slice(0,4),true),'Recent orders')}</section><section class="panel panel-pad branch-panel"><div class="section-head"><h2>${state.scope==='all'?'Your branches':'Your branch'}</h2>${go('Manage','branches','quiet')}</div>${selected.map(branch=>`<div class="branch-line"><div><strong>${branch.name}</strong><small><i class="status-dot"></i> Open · ${branch.staff} staff</small></div><div class="money">${money(branch.sales)}<small>${branch.orders} orders</small></div></div>`).join('')}</section></div>`;
}
function ordersPage(){
    let records=scoped(orders).filter(order=>state.tab==='All'||order.status===state.tab);
    records=records.filter(order=>`${order.id} ${order.name} ${order.branch}`.toLowerCase().includes(state.query.toLowerCase()));
    return `${heading('Orders',`${scoped(orders).length} orders`,button('Export','data-action="export"','','download'))}<section class="panel">${tabs(['All','Pending','Preparing','On the way','Delivered'].map(status=>[status,scoped(orders).filter(order=>status==='All'||order.status===status).length]))}<div class="filters">${search('Search order or customer')}<div class="filter-right"><span class="muted" style="font-size:11px">19 November 2026</span></div></div>${table(['Order','Customer','Branch','Status','<span class="sr-only">Total</span>','<span class="sr-only">Details</span>'],records.length?orderRows(records):emptyRow(6),'Seller orders')}<div class="table-foot"><span>${records.length} ${records.length===1?'order':'orders'}</span><span>Cash on delivery</span></div></section>`;
}
function orderDetail(id){
    const order=orders.find(item=>item.id===Number(id));
    if(!order){return missingPage('Order not found','orders');}
    const canConfirm=order.status==='Pending';
    return `<a href="#orders" class="back-link">${icon('back')} Orders</a>${heading(`JM-${order.id}`,statusBadge(order.status),order.rx?go(access().canReview?'Review prescription':'Check prescription',`prescriptions/${id}`,'primary','rx'):canConfirm?button('Confirm order',`data-action="confirm-order" data-id="${id}"`):button('View activity','data-action="activity"',''))}<div class="detail-grid"><div class="stack"><section class="panel"><div class="order-progress"><span class="complete"><i>${icon('check')}</i>Placed</span><b></b><span class="${order.status==='Pending'?'current':'complete'}"><i>2</i>Confirmed</span><b></b><span class="${order.status==='Preparing'?'current':''}"><i>3</i>Preparing</span><b></b><span class="${order.status==='Delivered'?'complete':''}"><i>4</i>Delivered</span></div><div class="table-panel-head section-head"><h2>Order items</h2><span class="muted">${order.items} items</span></div>${table(['Product','Qty','<span class="sr-only">Total</span>'],order.lines.map(line=>`<tr><td>${productCell(line.product)}</td><td>${line.quantity}</td><td class="money align-right">${money(line.product.price*line.quantity)}</td></tr>`).join(''),'Order items')}<div class="panel-pad" style="border-top:1px solid var(--line)"><div class="summary-line"><span class="muted">Subtotal</span><span class="money">${money(order.total)}</span></div><div class="summary-line"><span class="muted">Shipping</span><span class="money">${money(0)}</span></div><div class="summary-line total"><span>Total · Cash on delivery</span><span class="money">${money(order.total)}</span></div></div></section><section class="panel panel-pad"><h2>Activity</h2><ol class="timeline"><li><div>Order placed<small>19 November 2026 · ${order.time}</small></div></li><li><div>${order.rx?'Prescription uploaded':'Awaiting confirmation'}<small>${order.rx?'Waiting for a licensed pharmacist':order.branch}</small></div></li></ol></section></div><aside class="stack"><section class="panel panel-pad customer-info"><h2>Customer</h2><h3>${order.name}</h3><p>customer@example.test<br>+63 917 000 0000</p>${go('Message customer','messages','quiet','messages')}<hr class="form-divider"><h3>Delivery address</h3><p>24 Sample Street, Lahug<br>Cebu City, Cebu 6000</p><hr class="form-divider"><h3>Fulfilling branch</h3><p>${order.branch}</p></section>${order.rx?`<section class="panel panel-pad"><h2>Prescription required</h2><p class="muted" style="font-size:12px;margin-top:10px">Review before confirming this order.</p>${go(access().canReview?'Review prescription':'Check prescription',`prescriptions/${id}`,'quiet','arrow')}</section>`:''}</aside></div>`;
}
function prescriptionsPage(id){
    if(id){
        const order=orders.find(order=>order.id===Number(id));
        if(!order){return missingPage('Prescription not found','prescriptions');}
        return `<a href="#prescriptions" class="back-link">${icon('back')} Prescriptions</a>${heading(`JM-${id}`,badge('Awaiting review','amber'))}<div class="detail-grid"><section class="panel panel-pad"><div class="section-head"><h2>Prescription</h2><span class="muted">Uploaded today · ${order.time}</span></div><div class="rx-sheet"><h3>Illustrative prescription</h3><p>For ${order.name}</p><p class="rx-label">Rx</p><p>This placeholder represents a customer’s uploaded document.</p><div class="rx-watermark">DESIGN PREVIEW · NOT A MEDICAL DOCUMENT</div></div></section><aside><form class="panel panel-pad stack" data-form="prescription"><div><h2>Review</h2><p class="muted" style="font-size:12px;margin-top:8px">A valid pharmacist license is required.</p></div><div class="summary-line"><span class="muted">Customer</span><strong>${order.name}</strong></div><div class="summary-line"><span class="muted">Branch</span><strong>${order.branch}</strong></div><label class="check-label"><input type="checkbox" class="checkbox" required>Prescription is clear and complete</label><label class="field">Review note <span class="muted">(optional)</span><textarea rows="3" placeholder="Add a note"></textarea></label>${button('Approve prescription',`type="submit" data-id="${id}"`)}${button('Request clearer photo','type="button" data-action="clearer-photo"','')}${button('Reject prescription','type="button" data-action="reject-rx"','quiet danger')}</form></aside></div>`;
    }
    const records=scoped(orders).filter(order=>order.rx);
    return `${heading('Prescriptions',`${records.length} awaiting review`)}<div class="notice neutral">${icon('rx')}<span>Prescription decisions are reserved for licensed pharmacists.</span></div><section class="panel">${table(['Order','Customer','Branch','Received','Status','<span class="sr-only">Review</span>'],records.map(order=>`<tr><td><a class="table-link" href="#orders/${order.id}">JM-${order.id}</a></td><td><strong>${order.name}</strong></td><td>${order.branch}</td><td>${order.time}</td><td>${badge('Awaiting review','amber')}</td><td class="align-right">${go('Review',`prescriptions/${order.id}`,'small')}</td></tr>`).join('')||emptyRow(6),'Prescription review queue')}</section>`;
}
function productsPage(){
    let records=products.filter(product=>state.tab==='All'||product.status===state.tab);
    records=records.filter(product=>`${product.name} ${product.sku}`.toLowerCase().includes(state.query.toLowerCase()));
    if(state.category!=='All categories'){records=records.filter(product=>product.category===state.category);}
    return `${heading('Products',`${products.length} listings`,go('Add product','products/new','primary','plus'))}<section class="panel">${tabs(['All','Published','Draft','Under review','Suspended'].map(status=>[status,products.filter(product=>status==='All'||product.status===status).length]))}<div class="filters">${search('Search by name or SKU')}<div class="filter-right"><label class="sr-only" for="category-filter">Category</label><select id="category-filter">${['All categories',...new Set(products.map(product=>product.category))].map(category=>`<option ${category===state.category?'selected':''}>${category}</option>`).join('')}</select></div></div>${table(['Product','Category','Price','Stock','Status','<span class="sr-only">Edit</span>'],records.map(product=>`<tr><td><a href="#products/${product.id}" class="table-link">${productCell(product)}</a></td><td>${product.category}</td><td class="money">${money(product.price)}</td><td>${product.stock===0?badge('Sold out'):product.stock<10?badge(`${product.stock} left`,'amber'):`<span class="numeric">${product.stock}</span>`}</td><td>${statusBadge(product.status)}</td><td class="align-right">${go('Edit',`products/${product.id}`,'small')}</td></tr>`).join('')||emptyRow(6),'Product catalog')}<div class="table-foot"><span>${records.length} listings</span><span>Head-office catalog</span></div></section>`;
}
function productForm(id){
    const product=products.find(product=>product.id===Number(id));
    if(id!=='new'&&!product){return missingPage('Product not found','products');}
    const editing=Boolean(product);
    const locked=product?.status==='Under review';
    return `<a href="#products" class="back-link">${icon('back')} Products</a>${heading(editing?'Edit product':'Add product',editing?statusBadge(product.status):'')}${product?.status==='Under review'?`<div class="notice">${icon('info')}<span>This listing is under review. Withdraw it before editing.</span>${button('Withdraw',`data-action="withdraw" data-id="${product.id}"`,'quiet')}</div>`:''}<form data-form="product" data-id="${product?.id||''}"><fieldset class="form-grid" ${locked?'disabled':''}><div><section class="panel form-section"><h2>Product details</h2><div class="stack">${field('Product name',product?.name||'','text','name="productName" required placeholder="e.g. Vitamin C 500 mg"')}${field('SKU',product?.sku||'','text','name="sku" required placeholder="VIT-C-500"')}<div class="field-row">${selectField('Category',[product?.category||'Select category',...new Set(products.map(item=>item.category))])}${selectField('Condition',['New','Used'])}</div><label class="field">Description<textarea name="description" rows="5" placeholder="Describe your product">${editing?'Product description and packaging details.':''}</textarea></label></div></section><section class="panel form-section"><h2>Photos</h2><label class="upload">${icon('upload')}Upload product photos<small>Up to 8 images · JPG or PNG</small><input type="file" accept="image/jpeg,image/png" multiple aria-label="Choose product photos"></label></section><section class="panel form-section"><h2>Pricing & inventory</h2><div class="stack"><div class="field-row">${field('Price (₱)',editing?(product.price/100).toFixed(2):'','number','name="price" required min="0.01" step="0.01" placeholder="0.00"')}${field('Compare-at price (₱)','','number','min="0" step="0.01" placeholder="Optional"')}</div>${field('Stock quantity',product?.stock??0,'number','name="stock" min="0" step="1" required')}<label class="check-label"><input type="checkbox" class="checkbox" ${product?.rx?'checked':''}>Requires a prescription</label></div></section></div><aside class="panel form-side"><h2>Listing</h2><p>${editing?'Save changes to this listing.':'Save a draft before submitting your product for review.'}</p>${button(editing?'Save changes':'Save draft','type="submit"')}${go('Cancel','products','quiet')}<hr class="form-divider"><h3>Variants</h3><p>Offer options such as size or packaging.</p>${button('Add options','type="button" data-action="variants"','')}</aside></fieldset></form>`;
}
function stockPage(){
    const stockBranch=access().headOffice?(state.stockBranch||'Cebu Capitol'):state.scope;
    const records=products.filter(product=>`${product.name} ${product.sku}`.toLowerCase().includes(state.query.toLowerCase())).filter(product=>state.tab!=='Low stock'||product.stock<10);
    return `${heading('Branch stock',stockBranch,button('Save stock','type="submit" form="stock-form"'))}<div class="notice neutral">${icon('info')}<span>Stock is managed separately for each branch.</span><label class="sr-only" for="stock-branch">Stock branch</label><select id="stock-branch" style="width:auto;max-width:150px">${branches.map(branch=>`<option ${branch.name===stockBranch?'selected':''}>${branch.name}</option>`).join('')}</select></div><form id="stock-form" class="panel" data-form="stock">${tabs([['All',products.length],['Low stock',products.filter(product=>product.stock<10).length]])}<div class="filters">${search('Search by name or SKU')}</div>${table(['Product','Status','Available stock','New quantity'],records.map(product=>`<tr><td>${productCell(product)}</td><td>${product.stock===0?badge('Sold out'):product.stock<10?badge('Low stock','amber'):badge('In stock','green')}</td><td class="numeric">${product.stock}</td><td><input class="stock-input" aria-label="New quantity for ${product.name}" name="stock-${product.id}" type="number" min="0" step="1" value="${product.stock}" required></td></tr>`).join('')||emptyRow(4),'Branch inventory')}</form>`;
}
function returnsPage(){
    const records=[{id:'RT-0024',order:1041,name:'Daniel Tan',reason:'Incorrect item',branch:'IT Park',status:'Requested',total:18500},{id:'RT-0023',order:1043,name:'Luis Ramos',reason:'Damaged packaging',branch:'Cebu Capitol',status:'Resolved',total:18000}].filter(record=>state.scope==='all'||record.branch===state.scope).filter(record=>state.tab==='All'||record.status===state.tab).filter(record=>`${record.id} ${record.name}`.toLowerCase().includes(state.query.toLowerCase()));
    return `${heading('Returns')}<section class="panel">${tabs([['All'],['Requested'],['Resolved']])}<div class="filters">${search('Search return or customer')}</div>${table(['Return','Order','Customer','Reason','Amount','Status','<span class="sr-only">Details</span>'],records.map(record=>`<tr><td><strong>${record.id}</strong></td><td><a class="table-link" href="#orders/${record.order}">JM-${record.order}</a></td><td>${record.name}</td><td>${record.reason}</td><td class="money">${money(record.total)}</td><td>${statusBadge(record.status)}</td><td class="align-right">${button('Review',`data-action="return" data-id="${record.id}"`,'small')}</td></tr>`).join('')||emptyRow(7),'Returns')}</section>`;
}
function aliasesPage(){
    const records=[['Paracetamol','Acetaminophen','Paracetamol 500 mg'],['Ascorbic acid','Vitamin C','Vitamin C 500 mg'],['Isopropyl alcohol','Rubbing alcohol','Isopropyl alcohol 70%']].filter(row=>row.join(' ').toLowerCase().includes(state.query.toLowerCase()));
    return `${heading('Other names','',button('Add name','data-action="alias"','primary','plus'))}<section class="panel"><div class="filters">${search('Search ingredient or other name')}</div>${table(['Ingredient','Other name','Product','<span class="sr-only">Edit</span>'],records.map(row=>`<tr><td><strong>${row[0]}</strong></td><td>${row[1]}</td><td>${row[2]}</td><td class="align-right">${button('Edit',`data-action="alias" data-name="${row[1]}"`,'small')}</td></tr>`).join('')||emptyRow(4),'Ingredient aliases')}</section><p class="muted" style="font-size:11px;margin-top:18px">Help customers find the same ingredient by a familiar name.</p>`;
}
function messagesPage(){
    const ids=visibleConversationIds();
    if(!ids.length){return `${heading('Messages')}<section class="panel empty"><strong>No conversations yet</strong><p>Messages for your assigned branch will appear here.</p></section>`;}
    if(!ids.includes(state.conversation)){state.conversation=ids[0]??0;}
    const conversation=conversations[state.conversation];
    return `${heading('Messages',`${ids.length} conversations`)}<section class="panel messages-layout"><div class="conversation-list">${search('Search conversations','conversation-search')}${ids.map(index=>[conversations[index],index]).map(([chat,index])=>`<button class="conversation ${index===state.conversation?'active':''}" data-conversation="${index}" aria-pressed="${index===state.conversation}"><span class="avatar">${initials(chat.name)}</span><span><strong>${chat.name}</strong><small>${chat.preview}</small></span><time>${chat.time}</time></button>`).join('')}</div><div class="chat"><div class="chat-head"><span class="avatar">${initials(conversation.name)}</span><div><strong>${conversation.name}</strong><small>Customer</small></div></div><div class="chat-body"><span class="chat-date">Today, 19 November</span>${conversation.messages.map(([message,time,ours])=>`<div class="bubble ${ours?'ours':''}">${escapeHtml(message)}<time>${time}</time></div>`).join('')}</div><form class="chat-compose" data-form="message"><label class="sr-only" for="message-input">Message</label><input id="message-input" name="message" placeholder="Write a message…" required maxlength="2000" autocomplete="off"><button type="submit" class="primary" aria-label="Send message">${icon('send')}</button></form></div></section>`;
}
function reviewsPage(){
    const records=[{name:'Ana Dela Cruz',stars:5,product:'Vitamin C 500 mg',text:'Packed well and arrived on time. Thank you!',date:'18 Nov',reply:''},{name:'Luis Ramos',stars:5,product:'Paracetamol 500 mg',text:'Easy to order. Staff were helpful with my questions.',date:'17 Nov',reply:'Thank you, Luis. We’re happy to help!'},{name:'Bea Mendoza',stars:4,product:'Digital thermometer',text:'Works as expected. Delivery took a little longer than planned.',date:'15 Nov',reply:''}].filter(record=>state.tab!=='Unanswered'||!record.reply);
    return `${heading('Reviews')}<div class="review-desk"><section class="panel">${tabs([['All',3],['Unanswered',2]])}${records.map((record,index)=>`<article class="review"><div class="review-head"><span class="avatar">${initials(record.name)}</span><div><h2 class="reviewer-name">${record.name}</h2><small>Verified purchase · ${record.date}</small></div><span class="stars" role="img" aria-label="${record.stars} out of 5 stars">${Array.from({length:record.stars},()=>icon('star')).join('')}</span></div><div class="review-product">${record.product}</div><p>${record.text}</p>${record.reply?`<div class="review-reply"><strong>JMMart Pharmacy</strong><br>${record.reply}</div>`:`${button('Reply',`data-action="reply" data-id="${index}"`,'quiet')}<form class="reply-form" data-form="reply" id="reply-${index}" hidden><label class="sr-only" for="reply-text-${index}">Your reply</label><textarea id="reply-text-${index}" rows="3" placeholder="Write a public reply" required maxlength="1000"></textarea>${button('Post reply','type="submit"')}</form>`}</article>`).join('')}</section><aside class="panel panel-pad" style="align-self:start"><h2>Store rating</h2><div class="rating-summary" style="margin-top:18px"><span class="rating-number">4.7</span><div><span class="stars" role="img" aria-label="4.7 out of 5 stars">${Array.from({length:5},()=>icon('star')).join('')}</span><small class="muted" style="display:block;margin-top:7px">3 sample reviews</small></div></div><div class="rating-bars">${[[5,67,2],[4,33,1],[3,0,0],[2,0,0],[1,0,0]].map(([rating,percent,count])=>`<div class="rating-bar">${rating} ${icon('star')}<span><i style="width:${percent}%"></i></span><b>${count}</b></div>`).join('')}</div></aside></div>`;
}
function branchesPage(){
    return `${heading('Branches',`${chosenBranches().length} locations`,button('Add branch','data-action="branch"','primary','plus'))}<section class="branch-cards" aria-label="Store branches">${chosenBranches().map(branch=>`<article class="panel branch-card"><div class="branch-card-top">${icon('branches')}${badge('Open','green')}</div><div><h2>${branch.name}</h2><p>${branch.address}</p></div><div class="branch-facts"><span><small>Manager</small>${branch.manager}</span><span><small>Team</small>${branch.staff} members</span></div><div><h3 style="font-size:12px">Opening hours</h3><p style="min-height:0;margin-top:7px">${branch.hours}</p></div>${button('Manage branch',`data-action="manage-branch" data-branch="${branch.name}"`,'')}</article>`).join('')}</section>`;
}
function staffPage(){
    const records=staff.filter(person=>state.scope==='all'||person.branch===state.scope).filter(person=>`${person.name} ${person.email}`.toLowerCase().includes(state.query.toLowerCase()));
    return `${heading('Staff',`${records.length} team members`,button('Invite member','data-action="invite"','primary','plus'))}<section class="panel"><div class="filters">${search('Search team members')}</div>${table(['Member','Role','Branch','PRC license','Status','<span class="sr-only">Edit</span>'],records.map(person=>`<tr><td><span class="product-cell"><span class="staff-avatar">${initials(person.name)}</span><span><strong>${person.name}</strong><small>${person.email}</small></span></span></td><td>${person.role}</td><td>${person.branch}</td><td>${person.license==='—'?'<span class="muted">—</span>':badge(person.license,'green')}</td><td>${statusBadge(person.status)}</td><td class="align-right">${button('Edit',`data-action="edit-staff" data-name="${person.name}"`,'small')}</td></tr>`).join('')||emptyRow(6),'Seller staff')}</section>`;
}
function settingsNav(active){return `<nav class="settings-nav" aria-label="Store settings"><a href="#settings" ${active==='settings'?'aria-current="page"':''}>Store details</a><a href="#hours" ${active==='hours'?'aria-current="page"':''}>Opening hours</a><a href="#staff">Team & permissions</a><a href="#account" ${active==='account'?'aria-current="page"':''}>Your account</a></nav>`;}
function hoursPage(){
    const branch=chosenBranches()[0];
    return `${heading('Opening hours','',button('Save hours','type="submit" form="hours-form"'))}<div class="settings-layout">${settingsNav('hours')}<form class="panel form-section" id="hours-form" data-form="hours"><h2>${branch.name}</h2><div class="hours-row" style="padding-top:0"><span><strong>Open 24 hours</strong><small>Accept orders at any time.</small></span><input id="all-day" type="checkbox" class="toggle" aria-label="Open 24 hours"></div><div class="field-row" style="margin-top:25px">${field('Opens at','08:00','time','id="opens-at" required')}${field('Closes at','21:00','time','id="closes-at" required')}</div><p class="muted" style="font-size:11px;margin-top:20px">Orders placed outside these hours are accepted when the branch reopens.</p></form></div>`;
}
function settingsPage(){
    return `${heading('Store settings','',button('Save changes','type="submit" form="settings-form"'))}<div class="settings-layout">${settingsNav('settings')}<form id="settings-form" data-form="settings"><section class="panel form-section"><h2>Store details</h2><div class="stack"><div class="product-cell"><span class="store-avatar" style="width:54px;height:54px;font-size:16px">JP</span><label class="field">Store logo<input type="file" accept="image/*" aria-label="Upload store logo"></label></div>${field('Store name','JMMart Pharmacy','text','required')}${field('Store URL','jmmart.com/stores/jmmart-pharmacy','text','readonly')}<label class="field">About your store<textarea rows="3">Your neighborhood pharmacy in Cebu.</textarea></label></div></section><section class="panel form-section"><h2>Contact & location</h2><div class="stack"><div class="field-row">${field('Contact email','hello@example.test','email','required')}${field('Phone number','+63 917 000 0000','tel','required')}</div>${field('Street address','Osmeña Boulevard','text','required')}<div class="field-row">${field('City','Cebu City','text','required')}${field('Postal code','6000','text','required')}</div></div></section></form></div>`;
}
function accountPage(){
    return `${heading('Your account','',button('Save changes','type="submit" form="account-form"'))}<div class="settings-layout">${settingsNav('account')}<div><form class="panel form-section" id="account-form" data-form="account"><h2>Profile</h2><div class="stack"><div class="product-cell"><span class="avatar" style="width:54px;height:54px;font-size:15px">FR</span><label class="field">Profile photo<input type="file" accept="image/*" aria-label="Upload profile photo"></label></div>${field('Full name','Franil Reyes','text','required')}${field('Email','franil@example.test','email','required')}<div>${badge('Email verified','green')}</div></div></form><section class="panel form-section"><div class="section-head"><h2 style="margin:0">Security</h2>${button('Manage sign-in','data-action="security"','small')}</div><p class="muted" style="font-size:12px">Manage your password and connected sign-in methods.</p></section><section class="panel form-section"><h2>Notifications</h2><label class="check-label"><input class="checkbox" type="checkbox" checked>Order updates</label><label class="check-label"><input class="checkbox" type="checkbox" checked>Buyer messages</label><label class="check-label"><input class="checkbox" type="checkbox">Desktop notifications</label></section></div></div>`;
}
function missingPage(title,page='overview'){return `${heading(title)}<div class="panel empty"><p>Choose another record to continue exploring.</p>${go('Go back',page)}</div>`;}
function canOpenPage(path){
    const [page,id]=path.split('/'),permissions=access();
    if(['aliases','branches'].includes(page)||(page==='products'&&id==='new')){return permissions.canEditProducts;}
    if(['staff','hours','settings'].includes(page)){return permissions.canManage;}
    if(page==='prescriptions'&&!id){return permissions.canReview;}
    return true;
}
function visibleConversationIds(){
    return conversations.map((conversation,index)=>({conversation,index})).filter(({conversation})=>state.scope==='all'||orders.some(order=>order.name===conversation.name&&order.branch===state.scope)).map(({index})=>index);
}
function renderRoleShell(){
    const profile=roleProfiles[state.role],permissions=access();
    document.getElementById('preview-role').value=state.role;
    document.getElementById('license-preview').hidden=!permissions.canManage;
    document.getElementById('preview-license').checked=state.licensed;
    const scope=document.getElementById('branch-scope');
    scope.disabled=!permissions.headOffice&&state.role!=='manager';
    scope.value=state.scope;
    if(state.role==='manager'){
        scope.innerHTML='<option value="Cebu Capitol">Cebu Capitol · Branch manager</option><option value="all">Head office · Manager</option>';
    }else{
        scope.innerHTML='<option value="all">Head office · All branches</option>'+branches.filter(branch=>permissions.headOffice||branch.name===state.scope).map(branch=>`<option>${branch.name}</option>`).join('');
    }
    scope.value=state.scope;
    const profileButton=document.querySelector('.profile-button');
    profileButton.querySelector('.avatar').textContent=initials(profile.name);
    profileButton.querySelector('strong').textContent=profile.name;
    profileButton.querySelector('small').textContent=state.role==='manager'&&permissions.headOffice?'Manager':profile.label;
    document.querySelector('.avatar-button').textContent=initials(profile.name);
    document.querySelector('.store-identity>div>span').innerHTML=`<i class="status-dot"></i> ${permissions.headOffice?'Active store':escapeHtml(state.scope)}`;
    document.body.dataset.role=state.role;
}
function roleOverview(){
    const permissions=access(),records=scoped(orders),waiting=records.filter(order=>order.rx);
    const manager=state.role==='manager',pharmacist=state.role==='pharmacist';
    const title=pharmacist?'Prescription desk':manager?state.scope:'Today’s orders';
    const note=pharmacist||!manager?state.scope:badge('Open','green');
    const stats=pharmacist?[
        ['Awaiting review',waiting.length,'Your branch'],['Pre-checked',waiting.filter(order=>order.checked).length,'Ready for your decision'],['Needs a clearer photo',waiting.filter(order=>order.needsPhoto).length,'Waiting on the customer'],
    ]:manager?[
        ['Branch sales',money(branches.find(branch=>branch.name===state.scope).sales),'Last 30 days'],['To confirm',records.filter(order=>order.status==='Pending').length,'Today'],['Preparing',records.filter(order=>order.status==='Preparing').length,'Today'],['Low stock',2,'1 sold out'],
    ]:[
        ['To confirm',records.filter(order=>order.status==='Pending').length,'Today'],['Preparing',records.filter(order=>order.status==='Preparing').length,'Today'],['On the way',records.filter(order=>order.status==='On the way').length,'Today'],['Delivered',records.filter(order=>order.status==='Delivered').length,'Today'],
    ];
    const primary=pharmacist?go('Open prescription queue','prescriptions','primary','rx'):go('Open orders','orders','primary','arrow');
    const summary=`<section class="stats role-stats" style="--stat-columns:${stats.length}" aria-label="${pharmacist?'Prescription':'Branch order'} summary">${stats.map(([label,value,note])=>`<div class="stat"><div class="stat-label">${label}</div><div class="stat-value">${value}</div><div class="stat-foot">${note}</div></div>`).join('')}</section>`;
    const work=pharmacist?`<section class="panel"><div class="table-panel-head section-head"><h2>Awaiting your review</h2><span class="muted">${waiting.length} ${waiting.length===1?'prescription':'prescriptions'}</span></div>${table(['Order','Customer','Received','Status','<span class="sr-only">Review</span>'],waiting.map(order=>`<tr><td><a href="#orders/${order.id}" class="table-link">JM-${order.id}</a></td><td><strong>${order.name}</strong></td><td>${order.time}</td><td>${badge(order.checked?'Pre-checked':'Awaiting review','amber')}</td><td>${go('Review',`prescriptions/${order.id}`,'small')}</td></tr>`).join('')||`<tr><td colspan="5" class="empty"><strong>You’re all caught up</strong><p>No prescriptions are waiting for your decision.</p></td></tr>`,'Assigned prescription queue')}</section>`:`<section class="panel"><div class="table-panel-head section-head"><h2>Branch orders</h2>${go('View all','orders','quiet','arrow')}</div>${table(['Order','Customer','Status','<span class="sr-only">Total</span>','<span class="sr-only">Details</span>'],orderRows(records,true),'Assigned branch orders')}</section>`;
    const side=pharmacist?`<section class="panel panel-pad"><h2>Your license</h2><div class="license-status">${badge('Valid PRC license','green')}</div><dl class="role-facts"><div><dt>Expiry</dt><dd>31 Aug 2027</dd></div><div><dt>Assignment</dt><dd>${state.scope}</dd></div></dl></section><section class="panel panel-pad"><h2>Branch work</h2><div class="role-shortcuts">${go('View orders','orders','quiet','orders')}${go('Update stock','stock','quiet','stock')}</div></section>`:`<section class="panel panel-pad"><h2>Prescriptions</h2><p class="role-copy">${waiting.length} awaiting a pharmacist.</p>${waiting.map(order=>`<button class="task" data-go="prescriptions/${order.id}"><span class="task-count">${icon('rx')}</span><span><strong>JM-${order.id}</strong><small>${order.checked?'Pre-checked · pharmacist next':order.name}</small></span>${icon('chevron')}</button>`).join('')||'<p class="role-copy">No pending prescriptions.</p>'}</section><section class="panel panel-pad"><h2>Inventory</h2><p class="role-copy">1 low-stock product · 1 sold out</p>${go('Update branch stock','stock','quiet','arrow')}</section>${manager?`<section class="panel panel-pad"><h2>Branch team</h2><p class="role-copy">1 licensed pharmacist on duty</p>${go('Manage staff','staff','quiet','arrow')}</section>`:''}`;
    return `${heading(title,note,primary)}${summary}<div class="role-work-grid">${work}<aside class="stack">${side}</aside></div>`;
}
function readOnlyProduct(id){
    const product=products.find(product=>product.id===Number(id));
    if(!product){return missingPage('Product not found','products');}
    return `<a href="#products" class="back-link">${icon('back')} Products</a>${heading(product.name,badge('View only'))}<div class="detail-grid"><section class="panel panel-pad"><h2>Product details</h2><dl class="role-facts"><div><dt>SKU</dt><dd>${product.sku}</dd></div><div><dt>Category</dt><dd>${product.category}</dd></div><div><dt>Price</dt><dd>${money(product.price)}</dd></div><div><dt>Listing</dt><dd>${statusBadge(product.status)}</dd></div></dl></section><aside class="panel panel-pad" style="align-self:start"><h2>${state.scope} stock</h2><p class="role-stock-count">${product.stock}</p>${go('Update stock','stock','quiet','arrow')}<p class="role-copy">Product details are managed by head office.</p></aside></div>`;
}
function applyRoleToPage(page,id){
    const permissions=access();
    if(page==='overview'&&!permissions.canReview){
        const queue=main.querySelector('button[data-go="prescriptions"]');
        if(queue){queue.dataset.go='orders';queue.dataset.preset='Pending';queue.querySelector('strong').textContent='Awaiting pharmacist';}
    }
    if(page==='products'&&!id&&!permissions.canEditProducts){
        main.querySelector('.page-actions').innerHTML=badge('View only');
        main.querySelectorAll('button[data-go^="products/"]').forEach(control=>{control.textContent='Details';});
        const foot=main.querySelector('.table-foot>span:last-child');if(foot){foot.textContent='Catalog managed by head office';}
    }
    if(page==='prescriptions'&&id&&!permissions.canReview){
        const order=orders.find(order=>order.id===Number(id));
        const back=main.querySelector('.back-link');
        if(back){back.href=`#orders/${id}`;back.innerHTML=`${icon('back')} Order details`;}
        const form=main.querySelector('form[data-form="prescription"]');
        if(form&&order){form.outerHTML=`<section class="panel panel-pad"><h2>Prescription check</h2><p class="role-copy">Check that the image is clear and complete. A licensed pharmacist makes the decision.</p><div class="license-status">${badge(order.checked?'Checked · awaiting pharmacist':'Awaiting pre-check','amber')}</div><div class="role-shortcuts">${button('Mark as checked',`data-action="precheck" data-id="${id}"`)}${button('Request clearer photo','data-action="clearer-photo"','')}</div></section>`;}
        if(order?.checked){const check=main.querySelector('[data-action="precheck"]');if(check){check.disabled=true;check.textContent='Checked';}}
    }
    if(page==='returns'&&!permissions.canManage){
        main.querySelectorAll('[data-action="return"]').forEach(control=>{control.textContent='Details';});
    }
    if(page==='stock'&&!permissions.headOffice){
        const branch=main.querySelector('#stock-branch');if(branch){branch.innerHTML=`<option>${state.scope}</option>`;branch.disabled=true;}
    }
    if(page==='staff'&&!permissions.headOffice){
        main.querySelectorAll('[data-action="edit-staff"]').forEach(control=>{
            const person=staff.find(person=>person.name===control.dataset.name);
            if(person?.role==='Manager'||person?.role==='Owner'){control.disabled=true;control.textContent=person.name===roleProfiles[state.role].name?'You':'Manager';}
        });
    }
    if(page==='settings'&&!permissions.headOffice){
        main.querySelector('h1').textContent='Branch details';
        const branch=branches.find(branch=>branch.name===state.scope);
        const fields=main.querySelectorAll('#settings-form input');
        fields[1].value=`JMMart Pharmacy ${branch.name}`;
        fields[5].value=branch.address;
    }
    if(page==='account'){
        const profile=roleProfiles[state.role],inputs=main.querySelectorAll('#account-form input');
        inputs[1].value=profile.name;inputs[2].value=profile.email;
        main.querySelector('#account-form .avatar').textContent=initials(profile.name);
    }
    main.querySelectorAll('.settings-nav a').forEach(link=>{if(!canOpenPage(link.hash.slice(1))){link.remove();}});
}
function chooseRole(role){
    state.role=Object.hasOwn(roleProfiles,role)?role:'owner';state.scope=state.role==='owner'?'all':state.role==='staff'?'Mandaue':'Cebu Capitol';state.licensed=state.role==='pharmacist';state.page='overview';state.tab='All';state.query='';
    const url=new URL(location);url.searchParams.set('role',state.role);url.searchParams.delete('licensed');url.searchParams.delete('scope');url.hash='overview';history.replaceState(null,'',url);
    if(dialog.open){dialog.close();}render();closeMenu();window.scrollTo(0,0);
}
function render(){
    renderRoleShell();
    renderNav();
    const [page,id]=state.page.split('/');
    const pages={overview,orders:()=>id?orderDetail(id):ordersPage(),prescriptions:()=>prescriptionsPage(id),products:()=>id?productForm(id):productsPage(),stock:stockPage,returns:returnsPage,aliases:aliasesPage,messages:messagesPage,reviews:reviewsPage,branches:branchesPage,staff:staffPage,hours:hoursPage,settings:settingsPage,account:accountPage};
    const record=['orders','prescriptions'].includes(page)&&id?orders.find(order=>order.id===Number(id)):null;
    if(!canOpenPage(state.page)){
        main.innerHTML=missingPage('This page is outside your role');
    }else if(record&&state.scope!=='all'&&record.branch!==state.scope){
        main.innerHTML=missingPage('Order not found','orders');
    }else if(page==='overview'&&!access().headOffice){
        main.innerHTML=roleOverview();
    }else if(page==='products'&&id&&!access().canEditProducts){
        main.innerHTML=readOnlyProduct(id);
    }else{
        main.innerHTML=(pages[page]||(()=>missingPage('Page not found')))();applyRoleToPage(page,id);
    }
    const searchInput=document.getElementById('table-search');
    if(searchInput){searchInput.value=state.query;}
    document.title=`${main.querySelector('h1')?.textContent||'Seller'} · JMMart design preview`;
    hydrateIcons();resizeChart();
}
function hydrateIcons(){document.querySelectorAll('[data-icon]').forEach(element=>{element.innerHTML=icon(element.dataset.icon);});}
function navigate(page,preset){
    state.tab=preset||'All';state.pendingTab=preset;state.query='';state.category='All categories';
    if(location.hash.slice(1)===page){state.page=page;render();}else{location.hash=page;}
    closeMenu();
}
function closeMenu(){
    document.querySelector('.sidebar').classList.remove('open');document.querySelector('.menu-scrim').hidden=true;
    document.querySelector('[data-action="menu"]').setAttribute('aria-expanded','false');document.body.style.overflow='';
    if(innerWidth<=1000){document.querySelector('.sidebar').inert=true;}
}
function showDialog(title,html){
    returnFocus=document.activeElement;document.getElementById('dialog-title').textContent=title;document.getElementById('dialog-body').innerHTML=html;dialog.showModal();
}
function toast(message){
    const element=document.getElementById('toast');element.textContent=message;element.hidden=false;clearTimeout(toastTimer);toastTimer=setTimeout(()=>{element.hidden=true;},4200);
}
function setVariant(variant){
    state.variant=['studio','focus','compact'].includes(variant)?variant:'studio';document.body.dataset.variant=state.variant;
    document.querySelectorAll('button[data-variant]').forEach(button=>button.setAttribute('aria-pressed',button.dataset.variant===state.variant));
    const url=new URL(location);if(state.variant==='studio'){url.searchParams.delete('variant');}else{url.searchParams.set('variant',state.variant);}history.replaceState(null,'',url);resizeChart();
}
const dialogForm = (fields,submit='Save changes')=>`<form data-form="dialog" class="stack">${fields}<div class="form-actions">${button('Cancel','type="button" data-action="close-dialog"','')}${button(submit,'type="submit"')}</div></form>`;
document.addEventListener('click',event=>{
    const target=event.target.closest('button,a');if(!target){return;}
    if(target.classList.contains('skip-link')){event.preventDefault();main.focus();main.scrollIntoView({block:'start'});return;}
    if(target.dataset.go){navigate(target.dataset.go,target.dataset.preset);return;}
    if(target.dataset.variant){setVariant(target.dataset.variant);return;}
    if(target.dataset.tab){state.tab=target.dataset.tab;render();document.querySelector(`[data-tab="${state.tab}"]`)?.focus();return;}
    if(target.dataset.conversation!==undefined){const index=Number(target.dataset.conversation);if(visibleConversationIds().includes(index)){state.conversation=index;render();}return;}
    const action=target.dataset.action;
    if(['withdraw','variants','alias','branch'].includes(action)&&!access().canEditProducts){return;}
    if(['invite','edit-staff','manage-branch'].includes(action)&&!access().canManage){return;}
    if(action==='precheck'){
        const order=scoped(orders).find(order=>order.id===Number(target.dataset.id));
        if(order){order.checked=true;render();toast('Pre-checked. A pharmacist still needs to approve it.');}return;
    }
    if(action==='return'&&!access().canManage){showDialog('Return details','<p>This return is view only. An owner or manager handles the decision.</p><div class="form-actions">'+button('Done','data-action="close-dialog"')+'</div>');return;}

    if(action==='menu'){
        const open=!document.querySelector('.sidebar').classList.contains('open');document.querySelector('.sidebar').classList.toggle('open',open);document.querySelector('.sidebar').inert=!open;document.querySelector('.menu-scrim').hidden=!open;target.setAttribute('aria-expanded',open);document.body.style.overflow=open?'hidden':'';
        if(open){document.querySelector('#navigation a').focus();}return;
    }
    if(action==='close-menu'){closeMenu();document.querySelector('[data-action="menu"]').focus();return;}
    if(action==='close-dialog'){dialog.close();return;}
    if(action==='clear-filters'){state.query='';state.tab='All';state.category='All categories';render();return;}
    if(action==='notifications'){
        const records=scoped(orders).filter(order=>order.status==='Pending').slice(0,2);
        showDialog('Notifications',`<ul class="dialog-list">${records.map(order=>`<li><strong>Order JM-${order.id} is awaiting confirmation</strong><br>${order.name} <small>${order.branch}</small>${go('View order',`orders/${order.id}`,'quiet')}</li>`).join('')}<li><strong>Low-stock products</strong><small>${state.scope==='all'?'Cebu Capitol':state.scope}</small>${go('Update stock','stock','quiet')}</li></ul>`);return;
    }
    if(action==='storefront'){showDialog('JMMart Pharmacy',`<p>The public storefront opens from here in the live app.</p><div class="form-actions">${button('Back to workspace','data-action="close-dialog"')}</div>`);return;}
    if(action==='period'){showDialog('Reporting period',`<form data-form="period" class="stack">${selectField('Period',['Last 30 days','Last 7 days','Today'])}<div class="form-actions">${button('Apply','type="submit"')}</div><p>Sample values update for comparison.</p></form>`);return;}
    if(action==='confirm-order'){const order=orders.find(order=>order.id===Number(target.dataset.id));order.status='Preparing';render();toast('Order confirmed in this preview.');return;}
    if(action==='activity'){document.querySelector('.timeline')?.scrollIntoView({block:'center',behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth'});return;}
    if(action==='clearer-photo'){showDialog('Request clearer photo',dialogForm('<label class="field">Message to customer<textarea rows="3" required>Please upload a clearer photo of the full prescription.</textarea></label>','Send request'));return;}
    if(action==='reject-rx'){showDialog('Reject prescription',dialogForm('<label class="field">Reason<textarea rows="3" placeholder="Explain what needs to change" required></textarea></label>','Reject prescription'));return;}
    if(action==='return'){showDialog(`Return ${target.dataset.id}`,`<p>The customer reported an incorrect item.</p><hr class="form-divider">${dialogForm(`<label class="field">Decision<select><option>Request more information</option><option>Approve return</option><option>Decline return</option></select></label><label class="field">Note<textarea rows="3" required></textarea></label>`,'Save decision')}`);return;}
    if(action==='alias'){showDialog(target.dataset.name?'Edit other name':'Add other name',dialogForm(`${selectField('Ingredient',['Paracetamol','Ascorbic acid','Isopropyl alcohol'])}${field('Other name',target.dataset.name||'','text','required')}`));return;}
    if(action==='invite'){
        const permissions=access();
        showDialog('Invite team member',dialogForm(`${field('Email','','email','required placeholder="name@example.com"')}${selectField('Role',permissions.headOffice?['Staff','Manager']:['Staff'])}${selectField('Branch',permissions.headOffice?[...(state.role==='owner'?['Head office']:[]),...branches.map(branch=>branch.name)]:[state.scope])}${state.role==='owner'?'<label class="check-label"><input class="checkbox" type="checkbox">Licensed pharmacist</label>':''}`,'Send invitation'));return;
    }
    if(action==='edit-staff'){
        const person=staff.find(person=>person.name===target.dataset.name);
        if(!person){return;}
        const permissions=access();
        if(!permissions.headOffice&&['Owner','Manager'].includes(person.role)){return;}
        if(state.role!=='owner'&&person.branch==='Head office'){return;}
        const assignment=person.role==='Owner'?'':`${selectField('Role',permissions.headOffice?['Staff','Manager']:['Staff'])}${selectField('Branch',permissions.headOffice?[...branches.map(branch=>branch.name),...(state.role==='owner'?['Head office']:[])]:[state.scope])}`;
        const license=state.role==='owner'?`${field('PRC license number','','text','placeholder="For pharmacists"')}${field('License expiry','','date')}`:'<p class="muted">Pharmacist licenses are managed by the owner.</p>';
        showDialog(person.name,dialogForm(assignment+license));return;
    }
    if(action==='branch'){showDialog('Add branch',dialogForm(`${field('Branch name','','text','required')}${field('Street address','','text','required')}${field('City','','text','required')}`,'Create branch'));return;}
    if(action==='manage-branch'){state.scope=target.dataset.branch;document.getElementById('branch-scope').value=state.scope;navigate('settings');toast(`Showing ${state.scope}.`);return;}
    if(action==='reply'){document.getElementById(`reply-${target.dataset.id}`).hidden=false;target.hidden=true;document.querySelector(`#reply-${target.dataset.id} textarea`).focus();return;}
    if(action==='security'){showDialog('Sign-in & security',`<p>Your identity provider manages your password, linked accounts, and sign-in sessions.</p><div class="form-actions">${button('Done','data-action="close-dialog"')}</div>`);return;}
    if(action==='variants'){showDialog('Product options',dialogForm(`${selectField('Option name',['Size','Color'])}${field('Values','','text','required placeholder="e.g. 30 tablets, 60 tablets"')}`,'Add options'));return;}
    if(action==='withdraw'){const product=products.find(product=>product.id===Number(target.dataset.id));if(product){product.status='Draft';render();}toast('Submission withdrawn in this preview.');return;}
    if(action==='export'){
        const content=['Order,Customer,Branch,Status,Total',...scoped(orders).map(order=>[order.id,order.name,order.branch,order.status,(order.total/100).toFixed(2)].join(','))].join('\r\n');
        const url=URL.createObjectURL(new Blob([content],{type:'text/csv;charset=utf-8;'}));const link=document.createElement('a');link.href=url;link.download='jmmart-sample-orders.csv';link.click();setTimeout(()=>URL.revokeObjectURL(url),1000);toast('Sample orders exported.');
    }
});
document.addEventListener('click',event=>{if(event.target.closest('[data-go]')&&dialog.open){dialog.close();}});
document.addEventListener('input',event=>{
    if(event.target.id==='table-search'){
        const position=event.target.selectionStart;state.query=event.target.value;render();const input=document.getElementById('table-search');input.focus();try{input.setSelectionRange(position,position);}catch{/* Search fields do not expose selection in every browser. */}
    }
    if(event.target.id==='conversation-search'){const query=event.target.value.toLowerCase();document.querySelectorAll('.conversation').forEach(button=>{button.hidden=!button.textContent.toLowerCase().includes(query);});}
});
document.addEventListener('change',event=>{
    if(event.target.id==='preview-role'){chooseRole(event.target.value);return;}
    if(event.target.id==='preview-license'){
        state.licensed=access().canManage?event.target.checked:state.role==='pharmacist';
        const url=new URL(location);if(state.licensed){url.searchParams.set('licensed','1');}else{url.searchParams.delete('licensed');}history.replaceState(null,'',url);render();return;
    }
    if(event.target.id==='branch-scope'){
        if(state.role==='owner'||state.role==='manager'){
            state.scope=event.target.value;const url=new URL(location);if(state.role==='manager'){url.searchParams.set('scope',state.scope);}history.replaceState(null,'',url);render();
        }
    }
    if(event.target.id==='stock-branch'&&access().headOffice){state.stockBranch=event.target.value;render();}
    if(event.target.id==='category-filter'){state.category=event.target.value;render();}
    if(event.target.id==='all-day'){document.getElementById('opens-at').disabled=event.target.checked;document.getElementById('closes-at').disabled=event.target.checked;}
});
document.addEventListener('submit',event=>{
    const form=event.target.closest('form[data-form]');if(!form){return;}event.preventDefault();
    if(form.dataset.form==='period'){
        state.period=({'Last 30 days':'30 days','Last 7 days':'7 days',Today:'Today'})[form.querySelector('select').value];dialog.close();render();return;
    }
    if(form.dataset.form==='message'){
        const value=form.elements.message.value.trim();if(!value){return;}
        conversations[state.conversation].messages.push([value,'Now',true]);conversations[state.conversation].preview=value;render();document.getElementById('message-input').focus();document.querySelector('.chat-body').scrollTop=10000;return;
    }
    if(form.dataset.form==='reply'){
        const reply=document.createElement('div');reply.className='review-reply';const name=document.createElement('strong');name.textContent='JMMart Pharmacy';reply.append(name,document.createElement('br'),document.createTextNode(form.querySelector('textarea').value));form.replaceWith(reply);toast('Reply posted in this preview.');return;
    }
    if(form.dataset.form==='stock'){
        products.forEach(product=>{const input=form.elements[`stock-${product.id}`];if(input){product.stock=Number(input.value);}});render();toast('Stock saved in this preview.');return;
    }
    if(form.dataset.form==='product'){
        if(!access().canEditProducts){return;}
        const data=new FormData(form);let product=products.find(product=>product.id===Number(form.dataset.id));
        const value={name:data.get('productName'),sku:data.get('sku'),price:Math.round(Number(data.get('price'))*100),stock:Number(data.get('stock'))};
        if(product){Object.assign(product,value);}else{products.push({id:products.length+1,...value,category:'Over-the-counter',status:'Draft',icon:'products',tone:'',rx:false});}
        navigate('products');toast('Product saved in this preview.');return;
    }
    if(form.dataset.form==='prescription'){if(!access().canReview){return;}const order=orders.find(order=>order.id===Number(form.querySelector('[data-id]').dataset.id));if(order){order.rx=false;order.status='Preparing';}navigate('prescriptions');toast('Prescription approved in this preview.');return;}
    if(dialog.open){dialog.close();}toast('Saved in this preview. Live data is unchanged.');
});
document.addEventListener('keydown',event=>{
    if(event.key==='Escape'&&document.querySelector('.sidebar').classList.contains('open')){closeMenu();document.querySelector('[data-action="menu"]').focus();}
    const tab=event.target.closest('[role="tab"]');
    if(tab&&['ArrowLeft','ArrowRight','Home','End'].includes(event.key)){
        event.preventDefault();const all=[...document.querySelectorAll('[role="tab"]')];let index=all.indexOf(tab);index=event.key==='Home'?0:event.key==='End'?all.length-1:(index+(event.key==='ArrowRight'?1:-1)+all.length)%all.length;all[index].click();
    }
    if(event.key==='Tab'&&document.querySelector('.sidebar.open')&&innerWidth<=1000){
        const controls=[...document.querySelectorAll('.sidebar a,.sidebar button')].filter(element=>element.getClientRects().length);const first=controls[0],last=controls.at(-1);
        if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus();}else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus();}
    }
});
dialog.addEventListener('click',event=>{if(event.target===dialog){const bounds=dialog.getBoundingClientRect();if(event.clientX<bounds.left||event.clientX>bounds.right||event.clientY<bounds.top||event.clientY>bounds.bottom){dialog.close();}}});
dialog.addEventListener('close',()=>{if(returnFocus?.isConnected){returnFocus.focus();}});
window.addEventListener('hashchange',()=>{state.page=location.hash.slice(1)||'overview';state.query='';state.category='All categories';state.tab=state.pendingTab||'All';state.pendingTab=null;render();closeMenu();main.focus();window.scrollTo(0,0);});
window.addEventListener('resize',()=>{if(innerWidth>1000){document.querySelector('.sidebar').inert=false;closeMenu();}else if(!document.querySelector('.sidebar.open')){document.querySelector('.sidebar').inert=true;}resizeChart();});
state.page=location.hash.slice(1)||'overview';setVariant(state.variant);render();closeMenu();
