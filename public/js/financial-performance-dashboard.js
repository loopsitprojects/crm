/* Loops Financial Performance Dashboard (Dynamic CRM Integration) */
if (typeof window.DATA !== 'undefined' && typeof DATA === 'undefined') {
    var DATA = window.DATA;
}
const TEAM_MODE = true;

const MONTHS = ['Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec','Jan','Feb','Mar'];
const MONTH_NAMES = ['April','May','June','July','August','September','October','November','December','January','February','March'];
const MONTHS_CLOSED = 6; // April–September (Sep contribution only; other P&L Apr–Sep)

const COLORS = {
  Corporate:'#a78bfa', DM:'#5b8def', Creative:'#f97373', IT:'#23c285',
  good:'#23c285', bad:'#ef4d6b', warn:'#f4b740', accent:'#5b8def', muted:'#8aa0bf',
  group:'#22d3ee', pipe:'#a78bfa',
  Brands:'#5b8def', BD:'#f4b740', IT_sales:'#23c285'
};

const SBU_ORDER = ['Corporate','DM','Creative','IT'];
const SBU_LABEL = {Corporate:'Corporate',DM:'Digital (DM)',Creative:'Creative',IT:'IT'};

const CLOSED_MONTH_SET = new Set(['April','May','June','July','August','September']);
const MOM_MONTH_SET   = new Set(['April','May','June','July','August','September']);
const ALL_MONTHS_LIST = ['April','May','June','July','August','September'];
// Period filter — controls which YTD months feed aggregate widgets (KPIs, mix, top clients, etc.)
let ACTIVE_MONTHS = new Set(['April','May','June','July','August','September']);
// YTD closed-won baseline (Apr–Sep, matches P&L cutoff). Aug closed-won shown separately in Pipeline tab.
const allClosedTxnsYtd = DATA.transactions.filter(t => t.stage === 'Closed Won' && CLOSED_MONTH_SET.has(t.month));
// Time-series baseline (Apr–Sep) — used by MoM widgets, retention, AI charts (unaffected by filter)
const closedTxnsMoM = DATA.transactions.filter(t => t.stage === 'Closed Won' && MOM_MONTH_SET.has(t.month));
// Filterable scoped view — respects ACTIVE_MONTHS. Reassigned by applyPeriodFilter().
let closedTxns = allClosedTxnsYtd.filter(t => ACTIVE_MONTHS.has(t.month));
const pipeTxns = DATA.transactions.filter(t => t.stage === 'Finalising Term' || t.stage === 'Objection Handling');

const fmtCur = v => 'LKR ' + new Intl.NumberFormat('en-US',{maximumFractionDigits:0}).format(v||0);
const fmtShort = v => {
  if(v==null) return '—';
  const a = Math.abs(v);
  if(a>=1e9) return (v/1e9).toFixed(2)+'B';
  if(a>=1e6) return (v/1e6).toFixed(2)+'M';
  if(a>=1e3) return (v/1e3).toFixed(1)+'K';
  return v.toFixed(0);
};
const fmtPct = v => ((v||0)*100).toFixed(1)+'%';
// Target pro-rated to the active period (# months in filter)
const ytdTarget = annual => (annual || 0) * ACTIVE_MONTHS.size / 12;
// Sum a 12-month array over ACTIVE_MONTHS
const sumActive = arr => {
  if(!arr) return 0;
  let s = 0;
  MONTH_NAMES.forEach((m, i) => { if(ACTIVE_MONTHS.has(m) && arr[i] != null) s += arr[i]; });
  return s;
};

function renderGroupKpis(){
  const g = DATA.group;
  // Scoped Group P&L — sums monthly arrays over ACTIVE_MONTHS
  const scopedRev     = sumActive(DATA.group_monthly_revenue);
  const scopedContrib = sumActive(DATA.group_monthly_contribution_pl);
  const scopedHr      = sumActive(DATA.group_monthly_hr);
  const scopedCost    = sumActive(DATA.group_monthly_total_cost);
  const scopedNp      = sumActive(DATA.group_monthly_net_profit);
  const grossMargin   = scopedRev > 0 ? scopedContrib / scopedRev : 0;
  const yTgt = ytdTarget(g.target_annual);
  const yAch = yTgt > 0 ? scopedContrib / yTgt : 0;
  const yGap = yTgt - scopedContrib;
  const per  = scopeLabel();
  const baseKpis = [
    {label:'Revenue ('+per+')', val:fmtCur(scopedRev), sub:ACTIVE_MONTHS.size+' month'+(ACTIVE_MONTHS.size===1?'':'s')+' of P&L', cls:''},
    {label:'Contribution ('+per+')', val:fmtCur(scopedContrib), sub:'Margin '+fmtPct(grossMargin), cls:'up'},
    {label:'Achievement vs Period Target', val:fmtPct(yAch), sub:fmtCur(scopedContrib)+' / '+fmtShort(yTgt)+' target · Gap '+fmtCur(yGap), cls: yAch>=1?'up':yAch>=0.85?'warn':'down'},
    {label:'Closed-Won Deals', val:closedTxns.length.toString(), sub:per, cls:''},
  ];
  if(!TEAM_MODE){
    baseKpis.push({label:'Total Cost ('+per+')', val:fmtCur(scopedCost), sub:'HR '+fmtShort(scopedHr)+' + Other', cls:''});
    baseKpis.push({label:'Net Profit ('+per+')', val:fmtCur(scopedNp), sub:scopedNp>=0?'Positive bottomline':'Loss-making', cls:scopedNp>=0?'up':'down'});
    const hrRatio = scopedContrib > 0 ? scopedHr / scopedContrib : 0;
    const hrCls  = hrRatio <= 0.60 ? 'up' : hrRatio <= 0.70 ? 'warn' : 'down';
    const hrNote = hrRatio <= 0.60 ? 'Healthy (≤60%)' : hrRatio <= 0.70 ? 'Elevated (60–70%)' : 'High (>70%)';
    baseKpis.push({label:'HR / Contribution', val:fmtPct(hrRatio), sub:fmtShort(scopedHr)+' HR · '+fmtShort(scopedContrib)+' contrib · '+hrNote, cls:hrCls});
  }
  document.getElementById('groupKpis').innerHTML = baseKpis.map(k=>`
    <div class="card kpi-card">
      <div class="label">${k.label}</div>
      <div class="val">${k.val}</div>
      <div class="delta ${k.cls}">${k.sub}</div>
    </div>`).join('');
}

function renderSbuTable(){
  const tb = document.querySelector('#sbuTable tbody');
  const rows = SBU_ORDER.map(s=>{
    const d = DATA.departments[s];
    // Scoped values for this SBU
    const rev     = sumActive(d.monthly_revenue);
    const contrib = sumActive(d.monthly_contribution);
    const hr      = sumActive(d.monthly_hr);
    const dept    = sumActive(d.monthly_dept_cost);
    const np      = sumActive(d.monthly_net_profit);
    const projCost = rev - contrib;
    const tgt = ytdTarget(d.target_annual);
    const ach = tgt>0 ? contrib/tgt : null;
    const gap = tgt>0 ? tgt - contrib : null;
    const hasHr = d.hr_cost != null;
    const hasDept = d.dept_cost != null;
    let extraLeft = '', extraRight = '';
    if(!TEAM_MODE){
      extraLeft = `<td class="num">${fmtCur(projCost)}</td>`;
      const costCell = !hasDept ? '<td class="num" title="No HR / overhead allocated to Corporate in source">—</td>' : `<td class="num">${fmtCur(dept)}</td>`;
      let ratioCell;
      if(hasHr && contrib > 0){
        const r = hr / contrib;
        const c = r <= 0.60 ? 'var(--good)' : r <= 0.70 ? 'var(--warn)' : 'var(--bad)';
        ratioCell = `<td class="num" style="color:${c};font-weight:600" title="HR ${fmtCur(hr)} ÷ Contribution ${fmtCur(contrib)}">${fmtPct(r)}</td>`;
      } else {
        ratioCell = '<td class="num" title="No HR allocated">—</td>';
      }
      const npNote = d.corp_note ? ' title="No dept cost allocated → Net P/L equals Contribution"' : '';
      const npCell = `<td class="num"${npNote} style="color:${np>=0?'var(--good)':'var(--bad)'}">${fmtCur(np)}${d.corp_note?' *':''}</td>`;
      extraRight = costCell + ratioCell + npCell;
    }
    return `<tr>
      <td><span class="badge ${s==='Corporate'?'corp':s==='DM'?'dm':s==='Creative'?'crea':'it'}">${SBU_LABEL[s]}</span></td>
      <td class="num">${fmtCur(rev)}</td>
      ${extraLeft}
      <td class="num">${fmtCur(contrib)}</td>
      <td class="num">${tgt>0?fmtCur(tgt):'—'}</td>
      <td class="num">${ach!==null?fmtPct(ach):'—'}</td>
      <td class="num" style="color:${gap!==null && gap>0?'var(--bad)':'var(--good)'}">${gap!==null?fmtCur(gap):'—'}</td>
      ${extraRight}
    </tr>`;
  });
  const g = DATA.group;
  const gRev  = sumActive(DATA.group_monthly_revenue);
  const gCon  = sumActive(DATA.group_monthly_contribution_pl);
  const gHr   = sumActive(DATA.group_monthly_hr);
  const gCost = sumActive(DATA.group_monthly_total_cost);
  const gNp   = sumActive(DATA.group_monthly_net_profit);
  let extraLeftG = '', extraRightG = '';
  if(!TEAM_MODE){
    const groupProjCost = gRev - gCon;
    const groupHrRatio = gCon > 0 ? gHr / gCon : 0;
    const gRc = groupHrRatio <= 0.60 ? 'var(--good)' : groupHrRatio <= 0.70 ? 'var(--warn)' : 'var(--bad)';
    extraLeftG = `<td class="num">${fmtCur(groupProjCost)}</td>`;
    extraRightG = `<td class="num">${fmtCur(gCost - groupProjCost)}</td>
      <td class="num" style="color:${gRc};font-weight:600" title="HR ${fmtCur(gHr)} ÷ Contribution ${fmtCur(gCon)}">${fmtPct(groupHrRatio)}</td>
      <td class="num" style="color:${gNp>=0?'var(--good)':'var(--bad)'}">${fmtCur(gNp)}</td>`;
  }
  const gYtdTgt = ytdTarget(g.target_annual);
  const gAch = gYtdTgt > 0 ? gCon / gYtdTgt : 0;
  const gGap = gYtdTgt - gCon;
  const gGapColor = gGap>0 ? 'var(--bad)' : 'var(--good)';
  const gAchColor = gAch>=1 ? 'var(--good)' : gAch>=0.85 ? 'var(--warn)' : 'var(--bad)';
  rows.push(`<tr style="border-top:2px solid var(--line);font-weight:600">
    <td>GROUP TOTAL</td>
    <td class="num">${fmtCur(gRev)}</td>
    ${extraLeftG}
    <td class="num">${fmtCur(gCon)}</td>
    <td class="num">${fmtCur(gYtdTgt)}</td>
    <td class="num" style="color:${gAchColor}">${fmtPct(gAch)}</td>
    <td class="num" style="color:${gGapColor}">${fmtCur(gGap)}</td>
    ${extraRightG}
  </tr>`);
  tb.innerHTML = rows.join('');
}

const chartBaseOpts = {
  responsive:true, maintainAspectRatio:false,
  plugins:{
    legend:{labels:{color:'#cfd8e8',font:{size:11}}},
    tooltip:{
      callbacks:{
        label: ctx=>{
          let v;
          if(typeof ctx.parsed === 'number') v = ctx.parsed;
          else {
            const horiz = ctx.chart.options.indexAxis === 'y';
            v = horiz ? ctx.parsed.x : ctx.parsed.y;
          }
          return ctx.dataset.label ? ctx.dataset.label+': '+fmtCur(v) : fmtCur(v);
        }
      }
    }
  },
  scales:{
    x:{ticks:{color:'#8aa0bf',font:{size:11}}, grid:{color:'rgba(255,255,255,0.04)'}},
    y:{ticks:{color:'#8aa0bf',font:{size:11},callback:v=>fmtShort(v)}, grid:{color:'rgba(255,255,255,0.04)'}}
  }
};

function renderContribTargetChart(){
  const labels = ['Group', ...SBU_ORDER.map(s=>SBU_LABEL[s])];
  const actuals = [sumActive(DATA.group_monthly_contribution_pl), ...SBU_ORDER.map(s=>sumActive(DATA.departments[s].monthly_contribution))];
  const targets = [ytdTarget(DATA.group.target_annual), ...SBU_ORDER.map(s=>ytdTarget(DATA.departments[s].target_annual))];
  const keys = ['Group', ...SBU_ORDER];
  new Chart(document.getElementById('chartContribTarget'),{
    type:'bar',
    data:{labels,
      datasets:[
        {label:'YTD Target (Apr–Sep · annual÷12×4)', data:targets, backgroundColor:'rgba(91,141,239,0.25)', borderColor:COLORS.accent, borderWidth:1.5, borderRadius:4},
        {label:'YTD Contribution (Apr–Sep)', data:actuals, backgroundColor:COLORS.good, borderRadius:4}
      ]},
    options:{...chartBaseOpts,
      onClick:(e,els)=>{
        if(!els || !els.length) return;
        const idx = els[0].index;
        const label = labels[idx];
        const key = keys[idx];
        openValueList(`${label} — YTD Contribution vs Target`, [
          {label:'YTD Target (Apr–Sep)', value: targets[idx]},
          {label:'YTD Contribution', value: actuals[idx]},
          {label:'Gap', value: targets[idx] - actuals[idx]},
          {label:'Achievement', value: targets[idx] > 0 ? (actuals[idx]/targets[idx]*100).toFixed(1)+'%' : '—'}
        ], {formatter: v => typeof v === 'number' ? fmtCur(v) : v, hideTotal:true, labelHeader:'Metric'});
      }
    }
  });
}

function renderProfitChart(){
  if(TEAM_MODE) return;
  const labels = SBU_ORDER.map(s=>SBU_LABEL[s]);
  const contrib = SBU_ORDER.map(s=>sumActive(DATA.departments[s].monthly_contribution));
  const cost = SBU_ORDER.map(s=>sumActive(DATA.departments[s].monthly_dept_cost));
  const profit = SBU_ORDER.map(s=>sumActive(DATA.departments[s].monthly_net_profit));
  new Chart(document.getElementById('chartProfit'),{
    type:'bar',
    data:{labels,
      datasets:[
        {label:'Contribution', data:contrib, backgroundColor:COLORS.accent, borderRadius:4},
        {label:'Dept Cost (HR+overhead)', data:cost, backgroundColor:'rgba(239,77,107,0.55)', borderRadius:4},
        {label:'Net P/L', data:profit, type:'line', borderColor:COLORS.warn, backgroundColor:COLORS.warn, tension:0.25, pointRadius:5, fill:false}
      ]},
    options:{...chartBaseOpts,
      onClick:(e,els)=>{
        if(!els || !els.length) return;
        const idx = els[0].index;
        const sbu = SBU_ORDER[idx];
        openValueList(`${labels[idx]} — YTD Profitability`, [
          {label:'YTD Contribution', value: contrib[idx]},
          {label:'Dept Cost (HR + overhead)', value: cost[idx]},
          {label:'Net P/L', value: profit[idx]},
          {label:'HR (of dept cost)', value: DATA.departments[sbu].hr_cost}
        ], {formatter:fmtCur, hideTotal:true, labelHeader:'Metric'});
      }
    }
  });
}

function aggBy(txns, key, n){
  const m={};
  txns.forEach(t=>{ m[t[key]] = (m[t[key]]||0) + t.amount; });
  const arr = Object.entries(m).sort((a,b)=>b[1]-a[1]);
  return n ? arr.slice(0,n) : arr;
}

// Set of transaction keys that should be reclassified as 'AI' in category aggregations.
// Includes: workbook Revenue Category = 'AI', DM CAG, and historical AI Project List matches.
const AI_TXN_KEYS = (function(){
  const keys = new Set();
  const mkKey = t => t.brand + '|' + t.month + '|' + t.description + '|' + Math.round(t.amount);
  DATA.transactions.filter(t => t.stage==='Closed Won' && t.category==='AI').forEach(t => keys.add(mkKey(t)));
  DATA.transactions.filter(t => t.stage==='Closed Won' && t.department==='DM' && t.category==='CAG').forEach(t => keys.add(mkKey(t)));
  // Historical AI list — match main txns by brand+month, close amount
  const workbookAiBrandMonth = new Set(DATA.transactions.filter(t => t.stage==='Closed Won' && t.category==='AI').map(t => t.brand+'|'+t.month));
  (DATA.ai_projects || []).filter(t => !workbookAiBrandMonth.has(t.brand+'|'+t.month)).forEach(aiItem => {
    DATA.transactions.filter(t => t.stage==='Closed Won' && t.brand===aiItem.brand && t.month===aiItem.month
      && Math.abs(t.amount - aiItem.amount) < 1)
      .forEach(t => keys.add(mkKey(t)));
  });
  return keys;
})();
function effectiveCategory(t){
  const key = t.brand + '|' + t.month + '|' + t.description + '|' + Math.round(t.amount);
  return AI_TXN_KEYS.has(key) ? 'AI' : t.category;
}
// Aggregate by effective category (reclassifies AI-adjacent txns to 'AI')
function aggByEffectiveCategory(txns){
  const m = {};
  txns.forEach(t => { const c = effectiveCategory(t); m[c] = (m[c]||0) + t.amount; });
  return Object.entries(m).sort((a,b)=>b[1]-a[1]);
}

function renderBrandChart(canvasId, txns, n, onPick){
  n = n||10;
  const data = aggBy(txns,'brand',n);
  const opts = {...chartBaseOpts, indexAxis:'y', plugins:{...chartBaseOpts.plugins, legend:{display:false}},
      scales:{
        x:{ticks:{color:'#8aa0bf',font:{size:11},callback:v=>fmtShort(v)}, grid:{color:'rgba(255,255,255,0.04)'}},
        y:{ticks:{color:'#cfd8e8',font:{size:11}}, grid:{display:false}}
      }};
  if(onPick){ opts.onClick = (e,els)=>{ if(els && els.length){ onPick(data[els[0].index][0]); } }; }
  new Chart(document.getElementById(canvasId),{
    type:'bar',
    data:{labels:data.map(d=>d[0]),
      datasets:[{data:data.map(d=>d[1]), backgroundColor:COLORS.accent, borderRadius:4}]},
    options:opts
  });
}

// Brand-chart with expandable "Show all" toggle. Wraps renderBrandChart + wires the button.
function renderBrandChartExpandable(prefix, txns, defaultN, onPick){
  const totalBrands = new Set(txns.map(t => t.brand)).size;
  const canvasId = prefix;
  const wrap    = document.getElementById(prefix + '_wrap');
  const title   = document.getElementById(prefix + '_title');
  const toggle  = document.getElementById(prefix + '_toggle');
  if(!wrap) return;

  const draw = (expanded) => {
    const n = expanded ? totalBrands : Math.min(defaultN, totalBrands);
    // 24px per bar + 40px padding, minimum 240px
    wrap.style.height = Math.max(240, n * 24 + 40) + 'px';
    // Destroy existing chart on this canvas
    const canvas = document.getElementById(canvasId);
    const existing = Chart.getChart(canvas);
    if(existing) existing.destroy();
    renderBrandChart(canvasId, txns, n, onPick);
    if(title){
      const label = expanded ? 'all ' + totalBrands : 'top ' + n;
      title.textContent = title.textContent.replace(/(top \d+|all \d+)/, label);
      if(!/(top \d+|all \d+)/.test(title.textContent)) title.textContent = title.textContent + ' — ' + label;
    }
    if(toggle) toggle.textContent = expanded ? 'Show top ' + defaultN : 'Show all (' + totalBrands + ')';
  };

  draw(false);
  if(toggle){
    // Guard against double-binding on re-render (we rebuild DOM but not chart events per-render)
    const newToggle = toggle.cloneNode(true);
    toggle.parentNode.replaceChild(newToggle, toggle);
    let expanded = false;
    newToggle.addEventListener('click', () => { expanded = !expanded; draw(expanded); });
  }
}

function renderCatChart(canvasId, txns, onPick){
  // Use effective category so AI-related items (Workbook AI + DM CAG + historical AI list) roll up into 'AI'
  const data = aggByEffectiveCategory(txns);
  const palette = ['#5b8def','#23c285','#f97373','#a78bfa','#f4b740','#22d3ee','#f472b6','#94a3b8'];
  const opts = {responsive:true,maintainAspectRatio:false, plugins:{legend:{position:'right',labels:{color:'#cfd8e8',font:{size:11},boxWidth:12}},tooltip:chartBaseOpts.plugins.tooltip}};
  if(onPick){ opts.onClick = (e,els)=>{ if(els && els.length){ onPick(data[els[0].index][0]); } }; }
  new Chart(document.getElementById(canvasId),{
    type:'doughnut',
    data:{labels:data.map(d=>d[0]),
      datasets:[{data:data.map(d=>d[1]), backgroundColor:palette, borderColor:'#0b1220', borderWidth:2}]},
    options:opts
  });
}

function renderDeptChart(canvasId, txns, onPick){
  const data = aggBy(txns,'department');
  const colors = data.map(d=>COLORS[d[0]] || COLORS.muted);
  const opts = {responsive:true,maintainAspectRatio:false, plugins:{legend:{position:'right',labels:{color:'#cfd8e8',font:{size:11},boxWidth:12}},tooltip:chartBaseOpts.plugins.tooltip}};
  if(onPick){ opts.onClick = (e,els)=>{ if(els && els.length){ onPick(data[els[0].index][0]); } }; }
  new Chart(document.getElementById(canvasId),{
    type:'doughnut',
    data:{labels:data.map(d=>d[0]),
      datasets:[{data:data.map(d=>d[1]), backgroundColor:colors, borderColor:'#0b1220', borderWidth:2}]},
    options:opts
  });
}

// Wire a month <select> to a top-projects table. baseTitle is rewritten to reflect the current filter.
function bindTopFilter(prefix, allTxns, opts){
  opts = opts || {};
  const select = document.getElementById(prefix + '_filter');
  const titleEl = document.getElementById(prefix + '_title');
  const baseTitle = opts.baseTitle || (titleEl ? titleEl.textContent : '');
  const n = opts.n || 15;
  const includeSbu = opts.includeSbu !== false;
  const includeMonth = opts.includeMonth !== false;
  if(!select) return;
  const render = () => {
    const m = select.value;
    const filtered = (m === 'All') ? allTxns : allTxns.filter(t => t.month === m);
    if(titleEl){
      const scope = (m === 'All') ? 'Apr–Sep' : m;
      titleEl.textContent = /\(/.test(baseTitle)
        ? baseTitle.replace(/\(Apr–Sep\)/, '(' + scope + ')')
        : baseTitle + ' (' + scope + ')';
    }
    renderTopProjects(prefix, filtered, n, includeSbu, includeMonth);
  };
  select.addEventListener('change', render);
  render();
}

function renderTopProjects(tableId, txns, n, includeSbu, includeMonth){
  n = n||15; includeSbu = includeSbu!==false; includeMonth = includeMonth!==false;
  const tb = document.querySelector(`#${tableId} tbody`);
  const top = [...txns].sort((a,b)=>b.amount-a.amount).slice(0,n);
  tb.innerHTML = top.map(t=>{
    const monthCell = includeMonth ? `<td>${t.month}</td>` : '';
    const sbuCell = includeSbu ? `<td><span class="badge ${t.department==='Corporate'?'corp':t.department==='DM'?'dm':t.department==='Creative'?'crea':'it'}">${t.department}</span></td>` : '';
    return `<tr>
      ${monthCell}
      <td>${t.brand}</td>
      <td>${t.description}</td>
      ${sbuCell}
      <td>${t.category}</td>
      <td>${t.manager||'—'}</td>
      <td class="num">${fmtCur(t.amount)}</td>
    </tr>`;
  }).join('');
}

function buildSbuPanel(sbu){
  const d = DATA.departments[sbu];
  const txns = closedTxns.filter(t=>t.department===sbu);
  // Scoped values (respect ACTIVE_MONTHS)
  const sRev  = sumActive(d.monthly_revenue);
  const sCon  = sumActive(d.monthly_contribution);
  const sHr   = sumActive(d.monthly_hr);
  const sDept = sumActive(d.monthly_dept_cost);
  const sNp   = sumActive(d.monthly_net_profit);
  const sProjCost = sRev - sCon;
  const ytdTgt = ytdTarget(d.target_annual);
  const qTgt = d.target_quarterly;
  const ach = ytdTgt>0 ? sCon/ytdTgt : null;
  const gap = ytdTgt>0 ? ytdTgt - sCon : null;
  const margin = sRev>0 ? sCon/sRev : 0;
  const per = scopeLabel();

  let extraCards = '';
  let profitCardHtml = '';
  if(!TEAM_MODE){
    const costCard = d.dept_cost==null
      ? `<div class="card kpi-card"><div class="label">Dept Cost (HR + overhead)</div><div class="val" style="color:var(--muted)">—</div><div class="delta">No overhead allocated in source</div></div>`
      : `<div class="card kpi-card"><div class="label">Dept Cost (HR + overhead)</div><div class="val">${fmtCur(sDept)}</div><div class="delta">of which HR ${fmtShort(sHr)}</div></div>`;
    const npSubtitle = d.corp_note
      ? 'Equals Contribution (no overhead)'
      : (sNp>=0?'Profitable':'Loss-making');
    const npCard = `<div class="card kpi-card"><div class="label">Net Profit / Loss</div><div class="val" style="color:${sNp>=0?'var(--good)':'var(--bad)'}">${fmtCur(sNp)}</div><div class="delta ${sNp>=0?'up':'down'}">${npSubtitle}</div></div>`;
    const projCostCard = `<div class="card kpi-card"><div class="label">Project Costs</div><div class="val">${fmtCur(sProjCost)}</div><div class="delta">Production / pass-through (${sRev>0?fmtPct(sProjCost/sRev):'—'} of rev)</div></div>`;
    let hrRatioCard = '';
    if(d.hr_cost != null && sCon > 0){
      const r = sHr / sCon;
      const cls = r <= 0.60 ? 'up' : r <= 0.70 ? 'warn' : 'down';
      const color = r <= 0.60 ? 'var(--good)' : r <= 0.70 ? 'var(--warn)' : 'var(--bad)';
      const note = r <= 0.60 ? 'Healthy (≤60%)' : r <= 0.70 ? 'Elevated' : 'High — margin risk';
      hrRatioCard = `<div class="card kpi-card"><div class="label">HR / Contribution</div><div class="val" style="color:${color}">${fmtPct(r)}</div><div class="delta ${cls}">${fmtShort(sHr)} HR / ${fmtShort(sCon)} · ${note}</div></div>`;
    } else if(d.corp_note){
      hrRatioCard = `<div class="card kpi-card"><div class="label">HR / Contribution</div><div class="val" style="color:var(--muted)">—</div><div class="delta">No HR allocated to Corporate in source</div></div>`;
    }
    extraCards = projCostCard + costCard + npCard + hrRatioCard;
    profitCardHtml = `
      <div class="card">
        <h3>Profitability — Revenue → Contribution → Net (${per})</h3>
        <div class="chart-wrap"><canvas id="${sbu}_profitChart"></canvas></div>
      </div>`;
  }

  const html = `
    <div class="section-title"><span class="dot" style="background:${COLORS[sbu]}"></span>${SBU_LABEL[sbu]} — ${TEAM_MODE?'Revenue & Contribution':'Contribution & Profitability'} <span class="pill">${per}</span></div>
    <div class="grid kpi">
      <div class="card kpi-card"><div class="label">Revenue (${per})</div><div class="val">${fmtCur(sRev)}</div><div class="delta">${txns.length} closed-won deals</div></div>
      ${!TEAM_MODE ? extraCards.split('</div></div>')[0]+'</div></div>' : ''}
      <div class="card kpi-card"><div class="label">Contribution (${per})</div><div class="val">${fmtCur(sCon)}</div><div class="delta up">Margin ${fmtPct(margin)}</div></div>
      <div class="card kpi-card"><div class="label">Period Target</div><div class="val">${ytdTgt>0?fmtCur(ytdTgt):'—'}</div><div class="delta">${ytdTgt>0?'Q-target '+fmtShort(qTgt)+' · Annual '+fmtShort(d.target_annual):'No formal target'}</div></div>
      <div class="card kpi-card"><div class="label">Achievement vs Period Target</div><div class="val">${ach!==null?fmtPct(ach):'—'}</div><div class="delta ${ach!==null && ach>=1?'up':ach>=0.85?'warn':'down'}">Gap: ${gap!==null?fmtCur(gap):'—'}</div></div>
      ${!TEAM_MODE ? extraCards.split('</div></div>').slice(1).join('</div></div>') : ''}
    </div>

    <div class="grid cols-2" style="margin-top:14px">
      <div class="card">
        <h3>YTD Contribution (Apr–Sep) vs YTD Target</h3>
        <div class="chart-wrap"><canvas id="${sbu}_targetChart"></canvas></div>
        ${ytdTgt>0 ? `<div class="note">Achievement: <b>${fmtPct(ach)}</b> · Gap to YTD target: <b>${fmtCur(gap)}</b> · YTD target = annual÷12×4 mo.</div>` : '<div class="note">Corporate revenue captures items not booked under DM, Creative or IT (e.g. HR retainers, corporate films, ad-hoc CAGs).</div>'}
      </div>
      ${profitCardHtml}
    </div>

    <div class="section-title"><span class="dot" style="background:${COLORS[sbu]}"></span>${SBU_LABEL[sbu]} — Monthly Achievement vs Target</div>
    <div id="${sbu}_monthly"></div>

    <div class="section-title"><span class="dot" style="background:${COLORS[sbu]}"></span>${SBU_LABEL[sbu]} — Quarterly Performance vs Target <span class="pill">FY starts 1 Apr</span></div>
    <div id="${sbu}_quarterly"></div>

    ${(!TEAM_MODE && d.hr_cost != null) ? `
    <div class="section-title"><span class="dot" style="background:var(--bad)"></span>${SBU_LABEL[sbu]} — HR-to-Contribution Ratio <span class="pill">Leadership · Apr–Sep</span></div>
    <div id="${sbu}_hr_ratio"></div>` : ''}

    ${sbu === 'DM' ? `
    <div class="section-title"><span class="dot" style="background:${COLORS[sbu]}"></span>Retainer Book — Value &amp; Retention <span class="pill">Apr–Sep</span></div>
    <div id="${sbu}_retainers"></div>

    <div class="section-title"><span class="dot" style="background:#f472b6"></span>AI Video Contribution <span class="pill">Apr–Sep</span></div>
    <div id="${sbu}_ai"></div>` : ''}

    <div class="section-title"><span class="dot" style="background:${COLORS[sbu]}"></span>${SBU_LABEL[sbu]} — Project Analysis <span class="pill">Apr–Sep closed-won</span></div>
    <div class="grid cols-3">
      <div class="card">
        <div class="filter-row"><h3 id="${sbu}_brand_title">Client (Brand) breakdown — top 30</h3>
          <button class="filter-select" id="${sbu}_brand_toggle" style="cursor:pointer">Show all</button>
        </div>
        <div class="chart-wrap xtall" id="${sbu}_brand_wrap"><canvas id="${sbu}_brand"></canvas></div>
      </div>
      <div class="card"><h3>Project type breakdown</h3><div class="chart-wrap"><canvas id="${sbu}_cat"></canvas></div></div>
      <div class="card"><h3>Sales person attribution</h3><div class="chart-wrap"><canvas id="${sbu}_mgr"></canvas></div></div>
    </div>

    <div class="section-title" style="margin-top:20px"><span class="dot" style="background:${COLORS[sbu]}"></span>${SBU_LABEL[sbu]} — Contribution mix by project type — month-on-month <span class="pill">Apr–Sep closed-won</span></div>
    <div id="${sbu}_mom_type"></div>

    <div class="card" style="margin-top:14px;overflow-x:auto">
      <div class="filter-row">
        <h3 id="${sbu}_top_title">Top billed projects — ${SBU_LABEL[sbu]} (Apr–Sep)</h3>
        <label style="color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:0.5px">Month
          <select id="${sbu}_top_filter" class="filter-select" style="margin-left:6px">
            <option value="All">All (Apr–Sep)</option>
            <option value="April">April</option>
            <option value="May">May</option>
            <option value="June">June</option>
            <option value="July">July</option>
          </select>
        </label>
      </div>
      <table id="${sbu}_top"><thead><tr>
        <th>Month</th><th>Brand</th><th>Project</th><th>Type</th><th>Manager</th><th class="num">Amount (LKR)</th>
      </tr></thead><tbody></tbody></table>
    </div>
  `;
  document.getElementById('panel-'+sbu).innerHTML = html;

  new Chart(document.getElementById(`${sbu}_targetChart`),{
    type:'bar',
    data:{labels: ytdTgt>0 ? ['Period Target','Period Contribution'] : ['Revenue','Contribution'],
      datasets:[{data: ytdTgt>0 ? [ytdTgt, sCon] : [sRev, sCon],
        backgroundColor:[ytdTgt>0?'rgba(91,141,239,0.3)':COLORS.muted, COLORS[sbu]], borderRadius:4}]},
    options:{...chartBaseOpts, plugins:{...chartBaseOpts.plugins,legend:{display:false}},
      onClick:(e,els)=>{
        if(!els || !els.length) return;
        const items = ytdTgt>0
          ? [{label:'Period Target', value: ytdTgt},
             {label:'Period Contribution', value: sCon},
             {label:'Gap', value: ytdTgt - sCon},
             {label:'Achievement', value: (sCon/ytdTgt*100).toFixed(1)+'%'}]
          : [{label:'Revenue', value: sRev}, {label:'Contribution', value: sCon}];
        openValueList(`${SBU_LABEL[sbu]} — Contribution vs Target`, items, {formatter:v=>typeof v==='number'?fmtCur(v):v, hideTotal:true, labelHeader:'Metric'});
      }
    }
  });
  if(!TEAM_MODE){
    const hasDept = d.dept_cost!=null;
    const profitLabels = hasDept
      ? ['Revenue','− Project Cost','Contribution','− Dept Cost','Net P/L']
      : ['Revenue','− Project Cost','Contribution = Net P/L'];
    const profitData = hasDept
      ? [sRev, sProjCost, sCon, sDept, sNp]
      : [sRev, sProjCost, sCon];
    const profitColors = hasDept
      ? [COLORS.accent,'rgba(239,77,107,0.55)',COLORS.good,'rgba(239,77,107,0.85)',sNp>=0?COLORS.good:COLORS.bad]
      : [COLORS.accent,'rgba(239,77,107,0.55)',COLORS.good];
    new Chart(document.getElementById(`${sbu}_profitChart`),{
      type:'bar',
      data:{labels:profitLabels,
        datasets:[{data:profitData, backgroundColor:profitColors, borderRadius:4}]},
      options:{...chartBaseOpts, plugins:{...chartBaseOpts.plugins,legend:{display:false}},
        onClick:(e,els)=>{
          if(!els || !els.length) return;
          openValueList(`${SBU_LABEL[sbu]} — Waterfall (${per})`,
            profitLabels.map((l,i)=>({label:l, value: profitData[i]})),
            {formatter:fmtCur, hideTotal:true, labelHeader:'Step'});
        }
      }
    });
  }
  renderBrandChartExpandable(`${sbu}_brand`, txns, 30, brand =>
    openDrillDown(`${SBU_LABEL[sbu]} · Brand: ${brand}`, txns.filter(t=>t.brand===brand)));
  renderCatChart(`${sbu}_cat`, txns, cat =>
    openDrillDown(`${SBU_LABEL[sbu]} · Project type: ${cat}`, txns.filter(t=>effectiveCategory(t)===cat)));
  const mgrData = aggBy(txns,'manager');
  const palette = ['#5b8def','#23c285','#f97373','#a78bfa','#f4b740','#22d3ee','#f472b6','#94a3b8'];
  new Chart(document.getElementById(`${sbu}_mgr`),{
    type:'doughnut',
    data:{labels:mgrData.map(d=>d[0]),
      datasets:[{data:mgrData.map(d=>d[1]), backgroundColor:palette, borderColor:'#0b1220', borderWidth:2}]},
    options:{responsive:true,maintainAspectRatio:false,
      onClick:(e,els)=>{ if(els && els.length){ const mgr = mgrData[els[0].index][0]; openDrillDown(`${SBU_LABEL[sbu]} · Manager: ${mgr}`, txns.filter(t=>t.manager===mgr)); } },
      plugins:{legend:{position:'right',labels:{color:'#cfd8e8',font:{size:11},boxWidth:12}},tooltip:chartBaseOpts.plugins.tooltip}}
  });
  bindTopFilter(`${sbu}_top`, txns, {baseTitle:`Top billed projects — ${SBU_LABEL[sbu]} (Apr–Sep)`, includeSbu:false});

  // Month-on-month contribution by project type (Apr–Sep for wider trend)
  renderMoMTypeContribution(`${sbu}_mom_type`, closedTxnsMoM.filter(t=>t.department===sbu));

  // Monthly achievement widget for this SBU
  renderMonthlyAchievement(`${sbu}_monthly`, [
    {key:sbu, label:SBU_LABEL[sbu], color:COLORS[sbu], monthlyKey:sbu}
  ]);

  // Quarterly performance widget for this SBU
  renderQuarterlyPerformance(`${sbu}_quarterly`, [
    {key:sbu, label:SBU_LABEL[sbu], color:COLORS[sbu], quarterlyTarget: d.target_quarterly}
  ]);

  // Leadership-only: HR/Contribution ratio for this SBU (skip if no HR allocated)
  if(!TEAM_MODE && d.hr_cost != null && d.monthly_hr && d.monthly_contribution){
    renderHRRatio(`${sbu}_hr_ratio`, [
      {key:sbu, label:SBU_LABEL[sbu], color:COLORS[sbu], hrArr:d.monthly_hr, contribArr:d.monthly_contribution}
    ]);
  }

  // DM-only: retainer book + AI videos
  if(sbu === 'DM'){
    renderRetainerWidgets(`${sbu}_retainers`);
    renderAiVideosWidget(`${sbu}_ai`);
  }
}

// ====== Combined AI list — merges 3 sources (dedup by brand+month, workbook wins) ======
// 1. Workbook Revenue Category = 'AI' — authoritative going forward
// 2. AI Project List file — historical for months not in workbook AI
// 3. DM CAG txns — treated as AI-related per earlier convention
function buildCombinedAi(){
  const workbookAi = DATA.transactions
    .filter(t => t.stage==='Closed Won' && t.category==='AI')
    .map(t => ({month:t.month, brand:t.brand, description:t.description, amount:t.amount, person:'—', manager:t.manager, source:'Workbook AI'}));
  // Dedup AI list against workbook AI by (brand,month) — workbook wins if same brand+month
  const workbookKeys = new Set(workbookAi.map(t => t.brand + '|' + t.month));
  const list = (DATA.ai_projects || [])
    .filter(t => !workbookKeys.has(t.brand + '|' + t.month))
    .map(t => ({...t, source:'AI List (historical)'}));
  const dmCag = DATA.transactions
    .filter(t => t.stage==='Closed Won' && t.department==='DM' && t.category==='CAG')
    .map(t => ({month:t.month, brand:t.brand, description:t.description, amount:t.amount, person:'—', manager:t.manager, source:'DM CAG'}));
  return workbookAi.concat(list).concat(dmCag);
}

// ====== AI Videos widget (DM only) ======
function renderAiVideosWidget(containerId){
  const host = document.getElementById(containerId);
  if(!host || !DATA.ai_projects) return;
  const combined = buildCombinedAi();
  const months = ['April','May','June','July','August','September'];
  const YTD_MONTHS = new Set(['April','May','June','July']);
  const aiTotal = combined.reduce((s,t)=>s+t.amount,0);
  const aiYtd = combined.filter(t => YTD_MONTHS.has(t.month)).reduce((s,t)=>s+t.amount,0);
  const dmContribYtd = DATA.departments.DM.contribution;
  // DM contribution & Campaign contribution per month from transactions
  const dmMonthlyContrib = months.map(m => DATA.transactions.filter(t => t.stage==='Closed Won' && t.department==='DM' && t.month===m).reduce((s,t)=>s+t.amount,0));
  const dmCampaignMonthly = months.map(m => DATA.transactions.filter(t => t.stage==='Closed Won' && t.department==='DM' && t.category==='Campaign' && t.month===m).reduce((s,t)=>s+t.amount,0));
  const aiByMonth       = months.map(m => combined.filter(t=>t.month===m).reduce((s,t)=>s+t.amount,0));
  const workbookByMonth = months.map(m => combined.filter(t=>t.month===m && t.source==='Workbook AI').reduce((s,t)=>s+t.amount,0));
  const listByMonth     = months.map(m => combined.filter(t=>t.month===m && t.source==='AI List (historical)').reduce((s,t)=>s+t.amount,0));
  const cagByMonth      = months.map(m => combined.filter(t=>t.month===m && t.source==='DM CAG').reduce((s,t)=>s+t.amount,0));
  const dmCampaignTotal = dmCampaignMonthly.reduce((s,v)=>s+v,0);
  const aiVsCampaign = dmCampaignTotal>0 ? aiTotal/dmCampaignTotal : 0;

  host.innerHTML = `
    <div class="grid kpi" style="margin-bottom:14px" id="${containerId}_kpis"></div>
    <div class="grid cols-2">
      <div class="card">
        <h3>AI contribution — month on month</h3>
        <div class="chart-wrap"><canvas id="${containerId}_monthly"></canvas></div>
        <div class="note">Stacked: Workbook 'AI' category + historical AI Project List (deduped) + DM CAG. Note: the Project Type doughnut shows only Workbook AI (~2.89M) as a category — this widget is broader.</div>
      </div>
      <div class="card">
        <h3>AI vs DM Campaign contribution</h3>
        <div class="chart-wrap"><canvas id="${containerId}_vsCampaign"></canvas></div>
        <div class="note">Side-by-side by month. Note: AI overlaps with Campaign (most AI list items are tagged as Campaign in the workbook), so this is a scale comparison, not a mutually-exclusive split.</div>
      </div>
    </div>
    <div class="grid cols-2" style="margin-top:14px">
      <div class="card">
        <h3>AI vs Rest of DM — Apr–Sep total</h3>
        <div class="chart-wrap"><canvas id="${containerId}_vsRestDoughnut"></canvas></div>
        <div class="note">Share of DM contribution driven by AI (list + CAG) vs the rest of DM.</div>
      </div>
      <div class="card">
        <h3>AI vs Rest of DM — monthly</h3>
        <div class="chart-wrap"><canvas id="${containerId}_vsRestMonthly"></canvas></div>
        <div class="note">Stacked bar per month showing AI portion vs remainder of DM contribution.</div>
      </div>
    </div>
    <div class="card" style="margin-top:14px">
      <h3>AI contribution by creator</h3>
      <div class="chart-wrap"><canvas id="${containerId}_person"></canvas></div>
      <div class="note">Person credited with the AI creation. CAG entries not attributed to a specific creator show as "—".</div>
    </div>
    <div class="card" style="margin-top:14px;overflow-x:auto">
      <h3>AI project list (includes DM CAG)</h3>
      <table id="${containerId}_table"><thead><tr>
        <th>Month</th><th>Source</th><th>Brand</th><th>Project</th><th>Creator</th><th>Sales Manager</th><th class="num">Contribution (LKR)</th><th class="num">Share of AI</th>
      </tr></thead><tbody></tbody></table>
    </div>
  `;

  document.getElementById(containerId+'_kpis').innerHTML = [
    {label:'AI Contribution Apr–Sep', val:fmtCur(aiTotal), sub: combined.length+' items ('+combined.filter(t=>t.source==='Workbook AI').length+' workbook + '+combined.filter(t=>t.source==='AI List (historical)').length+' historical + '+combined.filter(t=>t.source==='DM CAG').length+' DM CAG)'},
    {label:'AI Share of DM (YTD Apr–Sep)', val: dmContribYtd>0 ? fmtPct(aiYtd/dmContribYtd) : '—', sub: fmtCur(aiYtd)+' / '+fmtCur(dmContribYtd)+' DM contribution'},
    {label:'AI vs DM Campaign', val: fmtPct(aiVsCampaign), sub:'AI '+fmtShort(aiTotal)+' vs Campaign '+fmtShort(dmCampaignTotal)+' (scale)'},
    {label:'Latest month (Sep)', val: fmtCur(aiByMonth[5]), sub: dmMonthlyContrib[5]>0 ? (aiByMonth[5]/dmMonthlyContrib[5]*100).toFixed(1)+'% of DM Sep' : '—'},
  ].map(k=>`<div class="card kpi-card">
    <div class="label">${k.label}</div>
    <div class="val">${k.val}</div>
    <div class="delta">${k.sub}</div>
  </div>`).join('');

  // Monthly chart: stacked bars — 3 sources
  new Chart(document.getElementById(containerId+'_monthly'),{
    type:'bar',
    data:{labels: months,
      datasets:[
        {label:'Workbook AI',              data: workbookByMonth, backgroundColor:'#f472b6', borderRadius:4},
        {label:'AI Project List (hist.)', data: listByMonth,     backgroundColor:'#fb7185', borderRadius:4},
        {label:'DM CAG',                   data: cagByMonth,      backgroundColor:'#a78bfa', borderRadius:4}
      ]},
    options:{
      responsive:true, maintainAspectRatio:false,
      layout:{padding:{top:22}},
      plugins:{legend:{position:'bottom', labels:{color:'#cfd8e8',font:{size:11}}},
        tooltip:{callbacks:{label: ctx => {
          const mi = ctx.dataIndex;
          const monthTotal = aiByMonth[mi];
          const share = dmMonthlyContrib[mi] > 0 ? (monthTotal/dmMonthlyContrib[mi]*100).toFixed(1)+'%' : '—';
          return ctx.dataset.label + ': ' + fmtCur(ctx.parsed.y) + ' (month total ' + fmtCur(monthTotal) + ' · ' + share + ' of DM)';
        }}}
      },
      onClick:(e,els)=>{
        if(!els || !els.length) return;
        const m = months[els[0].index];
        const monthAi = combined.filter(t=>t.month===m);
        openValueList(`AI (list + CAG) · ${m}`, monthAi.map(t=>({label: t.brand+' — '+t.description+' ['+t.source+']', value: t.amount})), {formatter:fmtCur, labelHeader:'Project'});
      },
      scales:{
        x:{stacked:true, ticks:{color:'#8aa0bf'}, grid:{color:'rgba(255,255,255,0.04)'}},
        y:{stacked:true, beginAtZero:true, ticks:{color:'#8aa0bf',callback:v=>fmtShort(v)}, grid:{color:'rgba(255,255,255,0.04)'}}
      }
    }
  });

  // AI vs Campaign monthly
  new Chart(document.getElementById(containerId+'_vsCampaign'),{
    type:'bar',
    data:{labels: months,
      datasets:[
        {label:'AI (list + CAG)',  data: aiByMonth,         backgroundColor:'#f472b6', borderRadius:4},
        {label:'DM Campaign',      data: dmCampaignMonthly, backgroundColor:COLORS.DM, borderRadius:4}
      ]},
    options:{
      responsive:true, maintainAspectRatio:false,
      plugins:{legend:{position:'bottom', labels:{color:'#cfd8e8',font:{size:11}}},
        tooltip:{callbacks:{label: ctx => fmtCur(ctx.parsed.y) + ' (' + ctx.dataset.label + ')'}}
      },
      onClick:(e,els)=>{
        if(!els || !els.length) return;
        const el = els[0]; const m = months[el.index];
        if(el.datasetIndex === 0){
          openValueList(`AI (list + CAG) · ${m}`, combined.filter(t=>t.month===m).map(t=>({label:t.brand+' — '+t.description+' ['+t.source+']', value:t.amount})), {formatter:fmtCur, labelHeader:'Project'});
        } else {
          const camp = DATA.transactions.filter(t => t.stage==='Closed Won' && t.department==='DM' && t.category==='Campaign' && t.month===m);
          openDrillDown(`DM Campaign · ${m}`, camp);
        }
      },
      scales:{
        x:{ticks:{color:'#8aa0bf'}, grid:{color:'rgba(255,255,255,0.04)'}},
        y:{beginAtZero:true, ticks:{color:'#8aa0bf',callback:v=>fmtShort(v)}, grid:{color:'rgba(255,255,255,0.04)'}}
      }
    }
  });

  // AI vs Rest of DM — total doughnut + monthly stacked
  const dmByMonth = months.map(m => DATA.transactions.filter(t => t.stage==='Closed Won' && t.department==='DM' && t.month===m).reduce((s,t)=>s+t.amount,0));
  const restByMonth = months.map((m, i) => Math.max(0, dmByMonth[i] - aiByMonth[i]));
  const dmTotal = dmByMonth.reduce((s,v)=>s+v,0);
  const restTotal = Math.max(0, dmTotal - aiTotal);

  new Chart(document.getElementById(containerId+'_vsRestDoughnut'),{
    type:'doughnut',
    data:{labels:['AI (list + CAG)','Rest of DM'],
      datasets:[{data:[aiTotal, restTotal], backgroundColor:['#f472b6', COLORS.DM], borderColor:'#0b1220', borderWidth:2}]},
    options:{responsive:true, maintainAspectRatio:false,
      plugins:{
        legend:{position:'bottom', labels:{color:'#cfd8e8',font:{size:11},boxWidth:12,padding:8}},
        tooltip:{callbacks:{label: ctx => {
          const tot = ctx.dataset.data.reduce((s,v)=>s+v,0);
          return ctx.label + ': ' + fmtCur(ctx.parsed) + ' (' + (tot>0?(ctx.parsed/tot*100).toFixed(1):'—') + '%)';
        }}}
      },
      onClick:(e,els)=>{
        if(!els || !els.length) return;
        const idx = els[0].index;
        if(idx === 0){
          openValueList('AI (list + CAG) — Apr–Sep', combined.map(t=>({label:t.month+' · '+t.brand+' — '+t.description+' ['+t.source+']', value:t.amount})));
        } else {
          // Rest of DM = DM txns minus items in combined AI (by exact match brand+month+amount)
          const aiKeys = new Set(combined.map(t => t.brand+'|'+t.month+'|'+Math.round(t.amount)));
          const restTxns = DATA.transactions.filter(t => t.stage==='Closed Won' && t.department==='DM' && months.indexOf(t.month) >= 0 && !aiKeys.has(t.brand+'|'+t.month+'|'+Math.round(t.amount)));
          openDrillDown('Rest of DM (non-AI) — Apr–Sep', restTxns);
        }
      }
    }
  });

  new Chart(document.getElementById(containerId+'_vsRestMonthly'),{
    type:'bar',
    data:{labels: months,
      datasets:[
        {label:'AI (list + CAG)', data: aiByMonth,   backgroundColor:'#f472b6', borderRadius:4},
        {label:'Rest of DM',      data: restByMonth, backgroundColor:COLORS.DM, borderRadius:4}
      ]},
    options:{
      responsive:true, maintainAspectRatio:false,
      plugins:{legend:{position:'bottom', labels:{color:'#cfd8e8',font:{size:11}}},
        tooltip:{callbacks:{label: ctx => {
          const mi = ctx.dataIndex;
          const mt = dmByMonth[mi];
          const share = mt>0 ? (ctx.parsed.y/mt*100).toFixed(1)+'%' : '—';
          return ctx.dataset.label + ': ' + fmtCur(ctx.parsed.y) + ' (' + share + ' of DM ' + months[mi] + ')';
        }}}
      },
      scales:{
        x:{stacked:true, ticks:{color:'#8aa0bf'}, grid:{color:'rgba(255,255,255,0.04)'}},
        y:{stacked:true, beginAtZero:true, ticks:{color:'#8aa0bf',callback:v=>fmtShort(v)}, grid:{color:'rgba(255,255,255,0.04)'}}
      }
    }
  });

  // Creator doughnut
  const byPerson = {};
  combined.forEach(t => byPerson[t.person] = (byPerson[t.person]||0) + t.amount);
  const pLabels = Object.keys(byPerson).sort((a,b)=>byPerson[b]-byPerson[a]);
  const pData = pLabels.map(k => byPerson[k]);
  const pColors = ['#f472b6','#a78bfa','#5b8def','#23c285','#f4b740','#94a3b8'];
  new Chart(document.getElementById(containerId+'_person'),{
    type:'doughnut',
    data:{labels:pLabels, datasets:[{data:pData, backgroundColor:pColors, borderColor:'#0b1220', borderWidth:2}]},
    options:{responsive:true, maintainAspectRatio:false,
      plugins:{
        legend:{position:'bottom', labels:{color:'#cfd8e8',font:{size:11},boxWidth:12,padding:8}},
        tooltip:{callbacks:{label: ctx => {
          const total = ctx.dataset.data.reduce((s,v)=>s+v,0);
          const share = total>0 ? (ctx.parsed/total*100).toFixed(1)+'%' : '—';
          return ctx.label + ': ' + fmtCur(ctx.parsed) + ' (' + share + ')';
        }}}
      },
      onClick:(e,els)=>{ if(els && els.length){ const p = pLabels[els[0].index]; openValueList(`AI projects · ${p}`, combined.filter(t=>t.person===p).map(t=>({label:t.month+' · '+t.brand+' — '+t.description+' ['+t.source+']', value:t.amount})), {formatter:fmtCur, labelHeader:'Project'}); } }
    }
  });

  const sortedAi = [...combined].sort((a,b)=>b.amount-a.amount);
  document.querySelector('#'+containerId+'_table tbody').innerHTML = sortedAi.map(t=>{
    const srcBadge = t.source === 'Workbook AI'
      ? '<span class="badge" style="background:rgba(244,114,182,0.22);color:#f9a8d4">Workbook AI</span>'
      : t.source === 'DM CAG'
        ? '<span class="badge" style="background:rgba(167,139,250,0.18);color:#c4b0ff">DM CAG</span>'
        : '<span class="badge" style="background:rgba(251,113,133,0.18);color:#fda4af">AI List (hist.)</span>';
    return `<tr>
      <td>${t.month}</td>
      <td>${srcBadge}</td>
      <td>${t.brand}</td>
      <td>${t.description}</td>
      <td>${t.person}</td>
      <td>${t.manager}</td>
      <td class="num">${fmtCur(t.amount)}</td>
      <td class="num">${fmtPct(t.amount/aiTotal)}</td>
    </tr>`;
  }).join('') + `<tr style="border-top:2px solid var(--line);font-weight:600">
    <td colspan="6">TOTAL (Apr–Sep)</td>
    <td class="num">${fmtCur(aiTotal)}</td>
    <td class="num">100.0%</td>
  </tr>`;
}

// ====== Quarterly Performance vs Target ======
// FY starts April 1: Q1=Apr-Jun, Q2=Jul-Sep, Q3=Oct-Dec, Q4=Jan-Mar
// entities: array of {key, label, color, quarterlyTarget} — key must match a DATA.monthly_actuals key
function renderQuarterlyPerformance(containerId, entities, opts){
  opts = opts || {};
  const host = document.getElementById(containerId);
  if(!host) return;

  const QUARTERS = [
    {name:'Q1', months:['April','May','June']},
    {name:'Q2', months:['July','August','September']},
    {name:'Q3', months:['October','November','December']},
    {name:'Q4', months:['January','February','March']},
  ];

  // Default entities = Group + all SBUs
  if(!entities) entities = [
    {key:'Group',    label:'Group',        color:COLORS.group,    quarterlyTarget: DATA.group.target_quarterly},
    {key:'DM',       label:'Digital (DM)', color:COLORS.DM,       quarterlyTarget: DATA.departments.DM.target_quarterly},
    {key:'Creative', label:'Creative',     color:COLORS.Creative, quarterlyTarget: DATA.departments.Creative.target_quarterly},
    {key:'IT',       label:'IT',           color:COLORS.IT,       quarterlyTarget: DATA.departments.IT.target_quarterly},
  ];

  // Compute actual per entity per quarter from monthly_actuals array
  function actualFor(entityKey, quarter){
    const arr = DATA.monthly_actuals[entityKey] || [];
    let sum = 0, monthsClosed = 0;
    quarter.months.forEach(m => {
      const idx = MONTH_NAMES.indexOf(m);
      if(idx >= 0 && arr[idx] != null){
        sum += arr[idx];
        monthsClosed++;
      }
    });
    return {sum, monthsClosed};
  }

  // Build rows: quarter x entity
  const quarterCards = QUARTERS.map(q => {
    const rows = entities.map(e => {
      const {sum, monthsClosed} = actualFor(e.key, q);
      const fullTarget = e.quarterlyTarget;
      const proratedTarget = fullTarget * (monthsClosed / q.months.length);
      const achFull = fullTarget > 0 ? sum / fullTarget : null;
      const achPace = proratedTarget > 0 ? sum / proratedTarget : null;
      return {...e, monthsClosed, sum, fullTarget, proratedTarget, achFull, achPace};
    });
    const anyData = rows.some(r => r.monthsClosed > 0);
    return {quarter:q, rows, anyData};
  });

  // Header summary — the two "live" quarters (Q1 done, Q2 in-progress)
  const liveQuarters = quarterCards.filter(qc => qc.anyData);

  // KPI cards for the current and previous live quarter
  // If there's a "Group" entity, feature that; otherwise feature the first entity in the list
  const primaryKey = (entities.find(e => e.key === 'Group') || entities[0]).key;
  const primaryLabel = (entities.find(e => e.key === 'Group') || entities[0]).label;
  const kpiCardsHtml = liveQuarters.map(qc => {
    const primaryRow = qc.rows.find(r => r.key === primaryKey);
    if(!primaryRow) return '';
    const complete = primaryRow.monthsClosed === qc.quarter.months.length;
    const label = qc.quarter.name + (complete ? ' (complete)' : ` (month ${primaryRow.monthsClosed} of ${qc.quarter.months.length})`);
    const ach = complete ? primaryRow.achFull : primaryRow.achPace;
    const color = ach == null ? 'var(--muted)' : ach >= 1 ? 'var(--good)' : ach >= 0.85 ? 'var(--warn)' : 'var(--bad)';
    const sub = complete
      ? `${fmtCur(primaryRow.sum)} / ${fmtShort(primaryRow.fullTarget)} target · Gap ${fmtCur(primaryRow.fullTarget - primaryRow.sum)}`
      : `${fmtCur(primaryRow.sum)} vs ${fmtCur(primaryRow.proratedTarget)} pro-rated target`;
    return `<div class="card kpi-card">
      <div class="label">${primaryLabel} — ${label}</div>
      <div class="val" style="color:${color}">${ach == null ? '—' : fmtPct(ach)}</div>
      <div class="delta" style="color:${color}">${sub}</div>
    </div>`;
  }).join('');

  // Chart: entities × live quarters, actual vs target
  const chartTitle = liveQuarters.length
    ? liveQuarters.map(q => q.quarter.name).join(' & ') + ' — Actual vs Target' + (entities.length > 1 ? ' by entity' : '')
    : 'Quarterly Actual vs Target';
  const chartHtml = `<div class="card">
    <h3>${chartTitle}</h3>
    <div class="chart-wrap tall"><canvas id="${containerId}_chart"></canvas></div>
    <div class="note">Solid = quarterly actual. Outlined = full-quarter target. In-progress quarters (fewer than 3 closed months) still compared against full target to show pace.</div>
  </div>`;

  // Table: full breakdown per quarter
  const tableRowsHtml = quarterCards.map(qc => {
    const complete = qc.rows[0].monthsClosed === qc.quarter.months.length;
    const monthsLabel = qc.rows[0].monthsClosed === 0
      ? '<span style="color:var(--muted)">Not yet started</span>'
      : complete
        ? '<span style="color:var(--good)">Complete</span>'
        : `<span style="color:var(--warn)">${qc.rows[0].monthsClosed} of ${qc.quarter.months.length} months closed</span>`;
    const headerRow = `<tr style="background:rgba(91,141,239,0.06)">
      <td colspan="7" style="font-weight:600">${qc.quarter.name} · ${qc.quarter.months[0]}–${qc.quarter.months[qc.quarter.months.length-1]} · ${monthsLabel}</td>
    </tr>`;
    if(qc.rows[0].monthsClosed === 0) return headerRow;
    const dataRows = qc.rows.map(r => {
      const complete = r.monthsClosed === qc.quarter.months.length;
      const ach = complete ? r.achFull : r.achPace;
      const achColor = ach == null ? 'var(--muted)' : ach >= 1 ? 'var(--good)' : ach >= 0.85 ? 'var(--warn)' : 'var(--bad)';
      const gap = complete ? (r.fullTarget - r.sum) : (r.proratedTarget - r.sum);
      const gapColor = gap > 0 ? 'var(--bad)' : 'var(--good)';
      const badgeCls = r.key==='DM'?'dm':r.key==='Creative'?'crea':r.key==='Corporate'?'corp':'it';
      const nameCell = r.key === 'Group'
        ? `<b>${r.label}</b>`
        : `<span class="badge ${badgeCls}">${r.label}</span>`;
      const hasTarget = r.fullTarget > 0;
      return `<tr>
        <td>${nameCell}</td>
        <td class="num">${fmtCur(r.sum)}</td>
        <td class="num">${hasTarget ? fmtCur(r.fullTarget) : '—'}</td>
        <td class="num" style="color:var(--muted)">${hasTarget ? fmtCur(r.proratedTarget) : '—'}</td>
        <td class="num" style="color:${achColor};font-weight:600">${ach == null || !hasTarget ? '—' : fmtPct(ach)}</td>
        <td class="num" style="color:${gapColor}">${hasTarget ? fmtCur(gap) : '—'}</td>
        <td style="color:var(--muted);font-size:11px">${!hasTarget ? 'No formal target' : complete ? 'vs full quarter' : 'vs pro-rated'}</td>
      </tr>`;
    }).join('');
    return headerRow + dataRows;
  }).join('');

  host.innerHTML = `
    <div class="grid kpi" style="margin-bottom:14px">${kpiCardsHtml}</div>
    ${chartHtml}
    <div class="card" style="margin-top:14px;overflow-x:auto">
      <h3>Quarter-by-quarter breakdown</h3>
      <table><thead><tr>
        <th>Entity</th>
        <th class="num">Actual</th>
        <th class="num">Full Q Target</th>
        <th class="num">Pro-rated Target</th>
        <th class="num">Achievement</th>
        <th class="num">Gap</th>
        <th>Basis</th>
      </tr></thead><tbody>${tableRowsHtml}</tbody></table>
      <div class="note">Achievement compares actual to <b>full quarter target</b> when the quarter is complete, and to the <b>pro-rated target</b> (monthly target × months closed) while in progress. FY starts 1 April.</div>
    </div>
  `;

  // Chart datasets — one dataset per quarter (actual), one per quarter (target outlined)
  const labels = entities.map(e => e.label);
  const qColors = [COLORS.good, COLORS.accent, COLORS.warn, COLORS.corp];
  const datasets = [];
  liveQuarters.forEach((qc, qi) => {
    const actuals = qc.rows.map(r => r.sum);
    const targets = qc.rows.map(r => r.fullTarget);
    const c = qColors[qi] || COLORS.accent;
    datasets.push({label: qc.quarter.name + ' Actual', data: actuals, backgroundColor: c, borderRadius: 4});
    datasets.push({label: qc.quarter.name + ' Target', data: targets, backgroundColor: 'transparent', borderColor: c, borderWidth: 1.5, borderRadius: 4});
  });
  new Chart(document.getElementById(containerId+'_chart'),{
    type:'bar',
    data:{labels, datasets},
    options:{...chartBaseOpts,
      plugins:{...chartBaseOpts.plugins,
        legend:{labels:{color:'#cfd8e8',font:{size:11}}}},
      onClick:(e,els)=>{
        if(!els || !els.length) return;
        const idx = els[0].index;
        const label = labels[idx];
        // Aggregate all quarters for the clicked entity
        const items = [];
        quarterCards.forEach(qc => {
          const r = qc.rows[idx];
          if(!r) return;
          const complete = r.monthsClosed === qc.quarter.months.length;
          const ach = complete ? r.achFull : r.achPace;
          const status = r.monthsClosed === 0 ? 'not started' : complete ? 'complete' : `${r.monthsClosed}/${qc.quarter.months.length} mo`;
          items.push({
            label: `${qc.quarter.name} Actual (${status})`,
            value: r.sum,
            sub: r.fullTarget > 0 && r.monthsClosed > 0 ? `Ach ${ach!=null?(ach*100).toFixed(1)+'%':'—'} · vs ${complete?'full':'pro-rated'} target ${fmtCur(complete?r.fullTarget:r.proratedTarget)}` : ''
          });
        });
        openValueList(`${label} — Quarterly Performance`, items, {formatter:fmtCur, hideTotal:true, labelHeader:'Quarter'});
      }
    }
  });
}

// ====== HR-to-Contribution Ratio widget (leadership-only) ======
// entities: [{key, label, color, hrArr, contribArr}] — each pair of 12-month arrays (Apr..Mar)
function renderHRRatio(containerId, entities){
  const host = document.getElementById(containerId);
  if(!host) return;
  const monthsShort = ['Apr','May','Jun','Jul'];
  const monthIdx = [0,1,2,3];  // first 4 slots (Apr–Sep closed)

  // KPI cards: YTD ratio per entity
  const kpiHtml = entities.map(en => {
    const hr = monthIdx.reduce((s,i)=>s + (en.hrArr[i] || 0), 0);
    const co = monthIdx.reduce((s,i)=>s + (en.contribArr[i] || 0), 0);
    if(co <= 0){
      return `<div class="card kpi-card">
        <div class="label">${en.label} — YTD HR / Contrib</div>
        <div class="val" style="color:var(--muted)">—</div>
        <div class="delta">No contribution</div>
      </div>`;
    }
    const r = hr / co;
    const color = r <= 0.60 ? 'var(--good)' : r <= 0.70 ? 'var(--warn)' : 'var(--bad)';
    const note = r <= 0.60 ? 'Healthy (≤60%)' : r <= 0.70 ? 'Elevated (60–70%)' : 'High (>70%)';
    return `<div class="card kpi-card">
      <div class="label">${en.label} — YTD HR / Contrib</div>
      <div class="val" style="color:${color}">${fmtPct(r)}</div>
      <div class="delta" style="color:${color}">${fmtShort(hr)} HR / ${fmtShort(co)} · ${note}</div>
    </div>`;
  }).join('');

  host.innerHTML = `
    <div class="grid kpi" style="margin-bottom:14px">${kpiHtml}</div>
    <div class="card">
      <h3>HR-to-Contribution ratio by month</h3>
      <div class="chart-wrap tall"><canvas id="${containerId}_chart"></canvas></div>
      <div class="note">Ratio = HR cost ÷ Contribution. Solid line = per-entity ratio. Dashed reference lines at 60% (healthy) and 70% (elevated). Click any point for detail.</div>
    </div>
    <div class="card" style="margin-top:14px;overflow-x:auto">
      <h3>Monthly HR / Contribution detail</h3>
      <table id="${containerId}_table"><thead><tr>
        <th>Entity</th>
        ${monthsShort.map(m=>`<th class="num">${m}</th>`).join('')}
        <th class="num">YTD</th>
      </tr></thead><tbody></tbody></table>
      <div class="note">Cells show HR ÷ Contribution ratio for that month. Colours: green ≤60%, amber 60–70%, red &gt;70%.</div>
    </div>
  `;

  // Line chart
  const datasets = entities.map(en => {
    const data = monthIdx.map(i => {
      const hr = en.hrArr[i], co = en.contribArr[i];
      if(hr == null || co == null || co <= 0) return null;
      return +(hr/co*100).toFixed(2);
    });
    return {
      label: en.label,
      data: data,
      borderColor: en.color,
      backgroundColor: en.color,
      pointBackgroundColor: en.color,
      pointRadius: 5,
      pointHoverRadius: 7,
      borderWidth: 2,
      tension: 0.25,
      fill: false,
      spanGaps: true
    };
  });
  // Reference lines: 60% and 70%
  datasets.push({label:'60% (healthy)', data: monthIdx.map(()=>60), borderColor:'rgba(35,194,133,0.5)', borderWidth:1, borderDash:[4,4], pointRadius:0, fill:false});
  datasets.push({label:'70% (elevated)', data: monthIdx.map(()=>70), borderColor:'rgba(244,183,64,0.5)', borderWidth:1, borderDash:[4,4], pointRadius:0, fill:false});

  new Chart(document.getElementById(containerId+'_chart'),{
    type:'line',
    data:{labels: monthsShort, datasets},
    options:{
      responsive:true, maintainAspectRatio:false,
      interaction:{mode:'index', intersect:false},
      plugins:{
        legend:{position:'bottom', labels:{color:'#cfd8e8',font:{size:11},boxWidth:12,padding:10,usePointStyle:true,pointStyle:'line'}},
        tooltip:{callbacks:{
          label: ctx => ctx.dataset.label + ': ' + (ctx.parsed.y != null ? ctx.parsed.y.toFixed(1)+'%' : '—')
        }}
      },
      onClick: (e, els) => {
        if(!els || !els.length) return;
        const mi = els[0].index;
        const monthName = MONTH_NAMES[mi];
        const items = entities.map(en => {
          const hr = en.hrArr[mi], co = en.contribArr[mi];
          const ratio = (hr != null && co > 0) ? (hr/co*100).toFixed(1)+'%' : '—';
          return {label: en.label, value: ratio, sub: hr != null ? `HR ${fmtCur(hr)} · Contribution ${co!=null?fmtCur(co):'—'}` : ''};
        });
        openValueList(`${monthName} — HR / Contribution`, items, {formatter:v=>v, hideTotal:true, currency:false, labelHeader:'Entity'});
      },
      scales:{
        x:{ticks:{color:'#8aa0bf',font:{size:11}}, grid:{color:'rgba(255,255,255,0.04)'}},
        y:{beginAtZero:true, suggestedMax:100, ticks:{color:'#8aa0bf',font:{size:11},callback:v=>v+'%'}, grid:{color:'rgba(255,255,255,0.04)'}}
      }
    }
  });

  // Table
  const rowsHtml = entities.map(en => {
    const cells = monthIdx.map(i => {
      const hr = en.hrArr[i], co = en.contribArr[i];
      if(hr == null || co == null || co <= 0) return '<td class="num" style="color:var(--muted)">—</td>';
      const r = hr/co;
      const c = r <= 0.60 ? 'var(--good)' : r <= 0.70 ? 'var(--warn)' : 'var(--bad)';
      return `<td class="num" style="color:${c};font-weight:600" title="HR ${fmtCur(hr)} ÷ Contribution ${fmtCur(co)}">${fmtPct(r)}</td>`;
    }).join('');
    const hrY = monthIdx.reduce((s,i)=>s + (en.hrArr[i]||0), 0);
    const coY = monthIdx.reduce((s,i)=>s + (en.contribArr[i]||0), 0);
    const yR = coY > 0 ? hrY/coY : null;
    const yC = yR == null ? 'var(--muted)' : yR <= 0.60 ? 'var(--good)' : yR <= 0.70 ? 'var(--warn)' : 'var(--bad)';
    const yCell = yR == null ? '<td class="num" style="color:var(--muted)">—</td>' : `<td class="num" style="color:${yC};font-weight:600" title="HR ${fmtCur(hrY)} ÷ Contribution ${fmtCur(coY)}">${fmtPct(yR)}</td>`;
    return `<tr>
      <td><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:${en.color};margin-right:8px;vertical-align:middle"></span>${en.label}</td>
      ${cells}
      ${yCell}
    </tr>`;
  }).join('');
  document.querySelector(`#${containerId}_table tbody`).innerHTML = rowsHtml;
}

// ====== Retainer widgets (DM only) ======
function renderRetainerWidgets(containerId){
  const host = document.getElementById(containerId);
  if(!host) return;
  const retainers = DATA.transactions.filter(t =>
    t.stage === 'Closed Won' && t.department === 'DM' && t.category === 'Retainer');
  const months = ['April','May','June','July','August','September'];

  // Monthly totals + brand-set snapshots
  const monthTotals = months.map(m => retainers.filter(t=>t.month===m).reduce((s,t)=>s+t.amount,0));
  const monthBrandSets = months.map(m => new Set(retainers.filter(t=>t.month===m).map(t=>t.brand)));
  const monthBrandCounts = monthBrandSets.map(s => s.size);

  // Retention analysis (each month vs previous)
  const flows = [];
  for(let i=1;i<months.length;i++){
    const prev = monthBrandSets[i-1], curr = monthBrandSets[i];
    const retained = [...prev].filter(b => curr.has(b));
    const lost = [...prev].filter(b => !curr.has(b));
    const won = [...curr].filter(b => !prev.has(b));
    flows.push({
      month: months[i],
      from: months[i-1],
      priorCount: prev.size,
      retainedCount: retained.length,
      lostCount: lost.length,
      wonCount: won.length,
      retentionRate: prev.size > 0 ? retained.length / prev.size : null,
      lost, won
    });
  }

  // Latest retention (Jul→Aug), YTD retention (avg), total lost, total won
  const totalLost = flows.reduce((s,f)=>s+f.lostCount,0);
  const totalWon  = flows.reduce((s,f)=>s+f.wonCount,0);
  const latest = flows[flows.length-1];
  const avgRetention = flows.filter(f=>f.retentionRate!==null).reduce((s,f)=>s+f.retentionRate,0) / flows.filter(f=>f.retentionRate!==null).length;
  const latestColor = latest && latest.retentionRate>=0.9 ? 'var(--good)' : latest && latest.retentionRate>=0.75 ? 'var(--warn)' : 'var(--bad)';
  const avgColor = avgRetention>=0.9 ? 'var(--good)' : avgRetention>=0.75 ? 'var(--warn)' : 'var(--bad)';
  const ytdRetainerValue = monthTotals.reduce((s,v)=>s+v,0);

  host.innerHTML = `
    <div class="note" style="margin-bottom:8px">Retention measures continuity of <b>existing</b> accounts only (retained ÷ prior-month active). Newly-won retainers are tracked separately under Growth — they don't count toward retention.</div>
    <div class="grid kpi" style="margin-bottom:14px">
      <div class="card kpi-card">
        <div class="label">Retainer Book — YTD Value</div>
        <div class="val">${fmtCur(ytdRetainerValue)}</div>
        <div class="delta">${retainers.length} retainer invoices · Apr–Sep</div>
      </div>
      <div class="card kpi-card">
        <div class="label">Latest Retention (${latest.from}→${latest.month})</div>
        <div class="val" style="color:${latestColor}">${fmtPct(latest.retentionRate)}</div>
        <div class="delta">${latest.retainedCount} kept of ${latest.priorCount} prior · ${latest.lostCount} lost</div>
      </div>
      <div class="card kpi-card">
        <div class="label">Avg Monthly Retention</div>
        <div class="val" style="color:${avgColor}">${fmtPct(avgRetention)}</div>
        <div class="delta">Avg of ${flows.length} handover months (existing accounts)</div>
      </div>
      <div class="card kpi-card">
        <div class="label">Retainers Lost (YTD)</div>
        <div class="val" style="color:${totalLost>0?'var(--bad)':'var(--muted)'}">${totalLost}</div>
        <div class="delta">Churn from existing book</div>
      </div>
      <div class="card kpi-card">
        <div class="label" style="color:#7fe1bd">Growth — Retainers Won (YTD)</div>
        <div class="val" style="color:var(--good)">+${totalWon}</div>
        <div class="delta">Net-new retainer accounts · not part of retention</div>
      </div>
    </div>

    <div class="grid cols-2">
      <div class="card">
        <h3>Month-on-month retainer value</h3>
        <div class="chart-wrap"><canvas id="${containerId}_valueChart"></canvas></div>
        <div class="note">Total retainer contribution by month (DM · Closed Won). Line shows active retainer count.</div>
      </div>
      <div class="card">
        <h3>Retention rate by month</h3>
        <div class="chart-wrap"><canvas id="${containerId}_rateChart"></canvas></div>
        <div class="note">% of prior-month retainers retained. Green ≥ 90%, amber 75–90%, red &lt; 75%.</div>
      </div>
    </div>

    <div class="card" style="margin-top:14px;overflow-x:auto">
      <h3>Retainer movement — brand-level</h3>
      <table id="${containerId}_flowTable"><thead><tr>
        <th>Month</th>
        <th class="num">Retainer Value</th>
        <th class="num">Active Brands</th>
        <th class="num">Retained</th>
        <th class="num">Lost</th>
        <th class="num">Won</th>
        <th class="num">Retention</th>
        <th>Lost brands</th>
        <th>Won brands</th>
      </tr></thead><tbody></tbody></table>
      <div class="note">"Lost" = brand had a retainer last month but not this month. "Won" = new retainer added this month.</div>
    </div>
  `;

  // Value chart (bars + line for count)
  new Chart(document.getElementById(`${containerId}_valueChart`),{
    data:{labels: months,
      datasets:[
        {type:'bar', label:'Retainer value', data:monthTotals, backgroundColor:COLORS.DM, borderRadius:4, yAxisID:'y'},
        {type:'line', label:'Active retainers (count)', data:monthBrandCounts, borderColor:COLORS.warn, backgroundColor:COLORS.warn, tension:0.25, pointRadius:5, fill:false, yAxisID:'y1'}
      ]},
    options:{
      responsive:true, maintainAspectRatio:false,
      plugins:{
        legend:{labels:{color:'#cfd8e8',font:{size:11}}},
        tooltip:{callbacks:{label: ctx => {
          const v = typeof ctx.parsed === 'number' ? ctx.parsed : ctx.parsed.y;
          if(ctx.dataset.label && ctx.dataset.label.indexOf('count')>=0) return ctx.dataset.label+': '+v;
          return ctx.dataset.label+': '+fmtCur(v);
        }}}
      },
      onClick:(e,els)=>{
        if(!els || !els.length) return;
        const m = months[els[0].index];
        openDrillDown(`${m} — Retainer projects`, retainers.filter(t=>t.month===m));
      },
      scales:{
        x:{ticks:{color:'#8aa0bf'}, grid:{color:'rgba(255,255,255,0.04)'}},
        y: {position:'left', beginAtZero:true, ticks:{color:'#8aa0bf', callback:v=>fmtShort(v)}, grid:{color:'rgba(255,255,255,0.04)'}, title:{display:true,text:'LKR value',color:'#8aa0bf',font:{size:10}}},
        y1:{position:'right', beginAtZero:true, ticks:{color:'#8aa0bf',stepSize:1}, grid:{display:false}, title:{display:true,text:'Brand count',color:'#8aa0bf',font:{size:10}}}
      }
    }
  });

  // Retention rate chart
  new Chart(document.getElementById(`${containerId}_rateChart`),{
    type:'bar',
    data:{labels: flows.map(f=>`${f.from.slice(0,3)}→${f.month.slice(0,3)}`),
      datasets:[{
        label:'Retention rate',
        data: flows.map(f=>f.retentionRate*100),
        backgroundColor: flows.map(f=>f.retentionRate>=0.9?COLORS.good:f.retentionRate>=0.75?COLORS.warn:COLORS.bad),
        borderRadius:4
      }]},
    options:{
      responsive:true, maintainAspectRatio:false,
      plugins:{legend:{display:false},
        tooltip:{callbacks:{label:ctx=>ctx.parsed.y.toFixed(1)+'%'}}
      },
      onClick:(e,els)=>{
        if(!els || !els.length) return;
        const f = flows[els[0].index];
        openValueList(`${f.from} → ${f.month} — Retention detail`, [
          {label:'Prior-month retainer count', value: f.priorCount, sub:''},
          {label:'Retained', value: f.retainedCount, sub:''},
          {label:'Lost', value: f.lostCount, sub: f.lost.join(', ') || '—'},
          {label:'Won', value: f.wonCount, sub: f.won.join(', ') || '—'},
          {label:'Retention rate', value: (f.retentionRate*100).toFixed(1)+'%', sub:''}
        ], {formatter: v => typeof v === 'number' ? v.toString() : v, hideTotal:true, labelHeader:'Metric', currency:false});
      },
      scales:{
        x:{ticks:{color:'#8aa0bf'}, grid:{color:'rgba(255,255,255,0.04)'}},
        y:{beginAtZero:true, max:100, ticks:{color:'#8aa0bf',callback:v=>v+'%'}, grid:{color:'rgba(255,255,255,0.04)'}}
      }
    }
  });

  // Flow table (includes month 1 with N/A retention)
  const flowRows = [
    `<tr><td><b>${months[0]}</b></td>
      <td class="num">${fmtCur(monthTotals[0])}</td>
      <td class="num">${monthBrandCounts[0]}</td>
      <td class="num">—</td><td class="num">—</td><td class="num">—</td>
      <td class="num">—</td><td>—</td><td>—</td></tr>`,
    ...flows.map((f,i)=>{
      const idx = i+1;
      const rateColor = f.retentionRate>=0.9?'var(--good)':f.retentionRate>=0.75?'var(--warn)':'var(--bad)';
      return `<tr>
        <td><b>${f.month}</b></td>
        <td class="num">${fmtCur(monthTotals[idx])}</td>
        <td class="num">${monthBrandCounts[idx]}</td>
        <td class="num" style="color:var(--good)">${f.retainedCount}</td>
        <td class="num" style="color:${f.lostCount>0?'var(--bad)':'var(--muted)'}">${f.lostCount}</td>
        <td class="num" style="color:${f.wonCount>0?'var(--good)':'var(--muted)'}">${f.wonCount}</td>
        <td class="num" style="color:${rateColor};font-weight:600">${fmtPct(f.retentionRate)}</td>
        <td style="color:var(--bad);font-size:12px">${f.lost.join(', ') || '—'}</td>
        <td style="color:var(--good);font-size:12px">${f.won.join(', ') || '—'}</td>
      </tr>`;
    })
  ];
  document.querySelector(`#${containerId}_flowTable tbody`).innerHTML = flowRows.join('');
}

function renderSalesUnitPanel(){
  const per = scopeLabel();
  const labels = Object.keys(DATA.sales_units);
  // Scoped sales-unit contribution — sum from monthly_actual over active months
  const contrib = labels.map(l => {
    const ma = DATA.sales_units[l].monthly_actual || {};
    return ALL_MONTHS_LIST.filter(m => ACTIVE_MONTHS.has(m)).reduce((s,m) => s + (ma[m] || 0), 0);
  });
  const tgtYtd = labels.map(l=>ytdTarget(DATA.sales_units[l].target_annual));
  new Chart(document.getElementById('su_targets'),{
    type:'bar',
    data:{labels,
      datasets:[
        {label:'Period Target (annual÷12 × '+ACTIVE_MONTHS.size+' mo)', data:tgtYtd, backgroundColor:'rgba(91,141,239,0.25)', borderColor:COLORS.accent, borderWidth:1.5, borderRadius:4},
        {label:'Contribution ('+per+')', data:contrib, backgroundColor:COLORS.good, borderRadius:4}
      ]},
    options:{...chartBaseOpts,
      onClick:(e,els)=>{
        if(!els || !els.length) return;
        const idx = els[0].index;
        const u = labels[idx];
        openValueList(`${u} — Contribution vs Target (${per})`, [
          {label:'Period Target', value: tgtYtd[idx]},
          {label:'Period Contribution', value: contrib[idx]},
          {label:'Gap', value: tgtYtd[idx] - contrib[idx]},
          {label:'Achievement', value: tgtYtd[idx]>0 ? (contrib[idx]/tgtYtd[idx]*100).toFixed(1)+'%' : '—'}
        ], {formatter:v=>typeof v==='number'?fmtCur(v):v, hideTotal:true, labelHeader:'Metric'});
      }
    }
  });
  // Individual contributions — scoped to active months (from individuals[i].monthly)
  const activeMArr = ALL_MONTHS_LIST.filter(m => ACTIVE_MONTHS.has(m));
  const indivScoped = DATA.individuals.map(i => {
    const c = activeMArr.reduce((s,m) => s + (i.monthly && i.monthly[m] ? i.monthly[m] : 0), 0);
    return {...i, scopedContrib: c};
  }).sort((a,b) => b.scopedContrib - a.scopedContrib);

  // Update section title dynamically to reflect the active period
  const titleEl = document.querySelector('#panel-sales h3');
  const indivH3 = Array.from(document.querySelectorAll('#panel-sales h3')).find(h => h.textContent.startsWith('Individual contribution'));
  if(indivH3) indivH3.textContent = 'Individual contribution — ' + per;

  new Chart(document.getElementById('su_indiv'),{
    type:'bar',
    data:{labels:indivScoped.map(i=>i.name),
      datasets:[{data:indivScoped.map(i=>i.scopedContrib), backgroundColor:COLORS.accent, borderRadius:4}]},
    options:{...chartBaseOpts, indexAxis:'y',
      onClick:(e,els)=>{ if(els && els.length){ const name = indivScoped[els[0].index].name; openDrillDown(`${name} — Projects (${per})`, txnsForPerson(name).filter(t => ACTIVE_MONTHS.has(t.month))); } },
      plugins:{...chartBaseOpts.plugins, legend:{display:false}},
      scales:{
        x:{ticks:{color:'#8aa0bf',font:{size:11},callback:v=>fmtShort(v)}, grid:{color:'rgba(255,255,255,0.04)'}},
        y:{ticks:{color:'#cfd8e8',font:{size:11}}, grid:{display:false}}
      }}
  });
  const su = DATA.sales_units;
  document.querySelector('#su_table tbody').innerHTML = Object.entries(su).map(([k,v])=>{
    const ytdTgt = ytdTarget(v.target_annual);
    const sContrib = activeMArr.reduce((s,m) => s + (v.monthly_actual && v.monthly_actual[m] ? v.monthly_actual[m] : 0), 0);
    const ach = ytdTgt>0 ? sContrib/ytdTgt : 0;
    const gap = ytdTgt - sContrib;
    return `<tr>
      <td><b>${k}</b></td>
      <td class="num">${fmtCur(sContrib)}</td>
      <td class="num">${fmtCur(ytdTgt)}</td>
      <td class="num" style="color:${ach>=1?'var(--good)':ach>=0.85?'var(--warn)':'var(--bad)'}">${fmtPct(ach)}</td>
      <td class="num" style="color:${gap>0?'var(--bad)':'var(--good)'}">${fmtCur(gap)}</td>
    </tr>`;
  }).join('');
  // Compute target for each individual using ACTIVE_MONTHS (contribution already computed above)
  const activeMonthsArr = activeMArr;
  const indivScopedTbl = DATA.individuals.map(i => {
    const contrib = activeMonthsArr.reduce((s,m) => s + (i.monthly && i.monthly[m] ? i.monthly[m] : 0), 0);
    const tgt = i.monthly_target ? activeMonthsArr.reduce((s,m) => {
      const v = i.monthly_target[m] != null ? i.monthly_target[m] : (i.monthly_target['June'] || 0);
      return s + v;
    }, 0) : 0;
    return {...i, contribScoped: contrib, tgtScoped: tgt, ach: tgt>0 ? contrib/tgt : null};
  });
  const total = indivScopedTbl.reduce((s,i)=>s+i.contribScoped,0);
  const maxV = Math.max(...indivScopedTbl.map(x=>x.contribScoped), 1);
  document.querySelector('#su_indiv_table tbody').innerHTML = indivScopedTbl.map(i=>{
    const achColor = i.ach==null?'var(--muted)':i.ach>=1?'var(--good)':i.ach>=0.85?'var(--warn)':'var(--bad)';
    const gap = i.tgtScoped - i.contribScoped;
    const gapColor = gap>0?'var(--bad)':'var(--good)';
    return `<tr>
    <td><b>${i.name}</b></td>
    <td class="num">${fmtCur(i.contribScoped)}</td>
    <td class="num">${i.tgtScoped>0?fmtCur(i.tgtScoped):'—'}</td>
    <td class="num" style="color:${achColor};font-weight:600">${i.ach==null?'—':fmtPct(i.ach)}</td>
    <td class="num" style="color:${i.tgtScoped>0?gapColor:'var(--muted)'}">${i.tgtScoped>0?fmtCur(gap):'—'}</td>
    <td>
      <div class="row-bar">
        <div class="barwrap"><div style="width:${maxV>0?(i.contribScoped/maxV*100).toFixed(1):0}%;background:${achColor}"></div></div>
        <div class="amt">${fmtPct(total>0?i.contribScoped/total:0)}</div>
      </div>
    </td>
  </tr>`;}).join('');

  if(TEAM_MODE) return;

  // ===== ROI section (leadership only) =====
  const roiUnits = Object.keys(DATA.sales_units);
  const scopedMonths = ACTIVE_MONTHS.size;
  const roiRows = roiUnits.map(name=>{
    const u = DATA.sales_units[name];
    const annCtc = u.ctc_monthly*12;
    const periodCtc = u.ctc_monthly*scopedMonths;
    const periodContribution = activeMArr.reduce((s,m) => s + (u.monthly_actual && u.monthly_actual[m] ? u.monthly_actual[m] : 0), 0);
    const mult = periodCtc > 0 ? periodContribution / periodCtc : 0;
    const runrate = scopedMonths > 0 ? (periodContribution/scopedMonths)*12 : 0;
    const pace = u.target_annual > 0 ? runrate / u.target_annual : 0;
    const tgtMult = annCtc > 0 ? u.target_annual / annCtc : 0;
    return {name, monthlyCtc:u.ctc_monthly, annCtc, ytdCtc: periodCtc, contribution: periodContribution, mult, runrate, target:u.target_annual, tgtMult, pace};
  });

  const roiKpiHost = document.getElementById('roiKpis');
  roiKpiHost.innerHTML = roiRows.map(r=>{
    const gap = r.mult - r.tgtMult;
    const gapPct = r.tgtMult>0 ? r.mult/r.tgtMult : 0;
    const gapColor = gapPct>=1 ? 'var(--good)' : gapPct>=0.85 ? 'var(--warn)' : 'var(--bad)';
    const gapLabel = gap>=0 ? `+${gap.toFixed(2)}× ahead` : `${gap.toFixed(2)}× behind target`;
    return `<div class="card kpi-card">
      <div class="label">${r.name} — YTD ROI vs Target</div>
      <div class="val">
        <span style="color:${gapColor}">${r.mult.toFixed(2)}×</span>
        <span style="color:var(--muted);font-size:14px;font-weight:400"> / ${r.tgtMult.toFixed(2)}× target</span>
      </div>
      <div class="delta" style="color:${gapColor}">${gapLabel} · ${fmtPct(gapPct)} of target</div>
    </div>`;
  }).join('');

  new Chart(document.getElementById('roi_mult'),{
    type:'bar',
    data:{labels:roiRows.map(r=>r.name),
      datasets:[
        {label:'Target Multiple (Annual Target ÷ CTC)', data:roiRows.map(r=>r.tgtMult),
          backgroundColor:'rgba(91,141,239,0.25)', borderColor:COLORS.accent, borderWidth:1.5, borderRadius:4},
        {label:'Actual Multiple (YTD Contribution ÷ YTD CTC)', data:roiRows.map(r=>r.mult),
          backgroundColor:roiRows.map(r=>{
            const ratio = r.mult/r.tgtMult;
            return ratio>=1 ? COLORS.good : ratio>=0.85 ? COLORS.warn : COLORS.bad;
          }), borderRadius:4}
      ]
    },
    options:{...chartBaseOpts,
      plugins:{...chartBaseOpts.plugins,
        tooltip:{callbacks:{label:ctx=>`${ctx.dataset.label}: ${ctx.parsed.y.toFixed(2)}×`}}},
      onClick:(e,els)=>{
        if(!els || !els.length) return;
        const r = roiRows[els[0].index];
        openValueList(`${r.name} — ROI Detail`, [
          {label:'Monthly CTC', value: r.monthlyCtc},
          {label:'YTD CTC (6 mo)', value: r.ytdCtc},
          {label:'Annual CTC', value: r.annCtc},
          {label:'YTD Contribution', value: r.contribution},
          {label:'Actual multiple', value: r.mult.toFixed(2)+'×'},
          {label:'Target multiple', value: r.tgtMult.toFixed(2)+'×'},
          {label:'Actual ÷ Target', value: (r.mult/r.tgtMult*100).toFixed(1)+'%'}
        ], {formatter:v=>typeof v==='number'?fmtCur(v):v, hideTotal:true, labelHeader:'Metric'});
      },
      scales:{
        x:{ticks:{color:'#8aa0bf'}, grid:{color:'rgba(255,255,255,0.04)'}},
        y:{beginAtZero:true, ticks:{color:'#8aa0bf', callback:v=>v+'×'}, grid:{color:'rgba(255,255,255,0.04)'}}
      }}
  });

  new Chart(document.getElementById('roi_runrate'),{
    type:'bar',
    data:{labels:roiRows.map(r=>r.name),
      datasets:[
        {label:'Annual Target', data:roiRows.map(r=>r.target), backgroundColor:'rgba(91,141,239,0.25)', borderColor:COLORS.accent, borderWidth:1.5, borderRadius:4},
        {label:'Run-rate (YTD ÷ 6 × 12)', data:roiRows.map(r=>r.runrate), backgroundColor:COLORS.good, borderRadius:4}
      ]},
    options:{...chartBaseOpts,
      onClick:(e,els)=>{
        if(!els || !els.length) return;
        const r = roiRows[els[0].index];
        openValueList(`${r.name} — Run-rate vs Annual Target`, [
          {label:'YTD Contribution (Apr–Sep)', value: r.contribution},
          {label:'Run-rate (annualised)', value: r.runrate},
          {label:'Annual Target', value: r.target},
          {label:'Pace to target', value: (r.pace*100).toFixed(1)+'%'},
          {label:'Gap to annual', value: r.target - r.runrate}
        ], {formatter:v=>typeof v==='number'?fmtCur(v):v, hideTotal:true, labelHeader:'Metric'});
      }
    }
  });

  const roiBody = document.querySelector('#roi_table tbody');
  roiBody.innerHTML = roiRows.map(r=>{
    const ratio = r.tgtMult>0 ? r.mult/r.tgtMult : 0;
    const ratioColor = ratio>=1 ? 'var(--good)' : ratio>=0.85 ? 'var(--warn)' : 'var(--bad)';
    const paceColor  = r.pace>=1 ? 'var(--good)' : r.pace>=0.85 ? 'var(--warn)' : 'var(--bad)';
    const verdictTxt = ratio>=1 ? 'On / above target' : ratio>=0.85 ? 'Within striking distance' : 'Below target';
    return `<tr>
      <td><b>${r.name}</b></td>
      <td class="num">${fmtCur(r.monthlyCtc)}</td>
      <td class="num">${fmtCur(r.ytdCtc)}</td>
      <td class="num">${fmtCur(r.contribution)}</td>
      <td class="num" style="font-weight:600">${r.mult.toFixed(2)}×</td>
      <td class="num" style="color:var(--accent);font-weight:600">${r.tgtMult.toFixed(2)}×</td>
      <td class="num" style="color:${ratioColor};font-weight:600">${fmtPct(ratio)}</td>
      <td class="num">${fmtCur(r.runrate)}</td>
      <td class="num">${fmtCur(r.target)}</td>
      <td class="num" style="color:${paceColor};font-weight:600">${fmtPct(r.pace)}</td>
      <td style="color:${ratioColor}">${verdictTxt}</td>
    </tr>`;
  }).join('');
}

// ====== Monthly Achievement vs Target widget (variable monthly targets) ======
function renderMonthlyAchievement(containerId, entities){
  const host = document.getElementById(containerId);
  if(!host) return;
  const cardHtml = entities.map(e=>{
    const actuals = DATA.monthly_actuals[e.monthlyKey] || [];
    const targets = DATA.monthly_targets[e.monthlyKey] || [];
    const closedActuals = actuals.slice(0,MONTHS_CLOSED);
    const ytdActual = closedActuals.reduce((s,v)=>s+(v||0),0);
    const ytdTarget = targets.slice(0,MONTHS_CLOSED).reduce((s,v)=>s+(v||0),0);
    if(ytdTarget<=0){
      return `<div class="card kpi-card">
        <div class="label">${e.label} — Monthly Target</div>
        <div class="val" style="color:var(--muted)">—</div>
        <div class="delta">No formal contribution target</div>
      </div>`;
    }
    const ach = ytdActual/ytdTarget;
    const color = ach>=1 ? 'var(--good)' : ach>=0.85 ? 'var(--warn)' : 'var(--bad)';
    const gap = ytdTarget - ytdActual;
    const avgMonthly = ytdTarget/MONTHS_CLOSED;
    return `<div class="card kpi-card">
      <div class="label">${e.label} — Avg Monthly Target</div>
      <div class="val">${fmtCur(avgMonthly)}</div>
      <div class="delta" style="color:${color}">
        ${MONTHS_CLOSED} mo closed · ${fmtPct(ach)} of pace · gap ${fmtCur(gap)}
      </div>
    </div>`;
  }).join('');

  const hasAnyTarget = entities.some(e=>{
    const t = DATA.monthly_targets[e.monthlyKey] || [];
    return t.some(v=>v>0);
  });
  host.innerHTML = `
    <div class="grid kpi" style="margin-bottom:14px">${cardHtml}</div>
    ${hasAnyTarget ? `<div class="card">
      <h3>12-Month Achievement Tracker</h3>
      <div class="chart-wrap tall"><canvas id="${containerId}_chart"></canvas></div>
      <div class="note">Bars = monthly closed-won contribution. Dashed line = monthly target (from Sales Target sheet — note Jun targets shift slightly). Future months populate as data is added.</div>
    </div>` : ''}
  `;
  if(!hasAnyTarget) return;

  const datasets = [];
  entities.forEach(e=>{
    const targets = DATA.monthly_targets[e.monthlyKey] || Array(12).fill(null);
    if(!targets.some(v=>v>0)) return;
    const actuals = DATA.monthly_actuals[e.monthlyKey] || Array(12).fill(null);
    datasets.push({
      label: e.label + ' — Actual',
      data: actuals,
      backgroundColor: e.color,
      borderRadius: 4,
      type: 'bar'
    });
    datasets.push({
      label: e.label + ' — Target',
      data: targets,
      type: 'line',
      borderColor: e.color,
      backgroundColor: 'transparent',
      borderDash: [6,4],
      borderWidth: 2,
      pointRadius: 0,
      fill: false,
      tension: 0
    });
  });

  new Chart(document.getElementById(containerId+'_chart'),{
    data:{labels:MONTHS, datasets},
    options:{...chartBaseOpts,
      plugins:{...chartBaseOpts.plugins,
        legend:{labels:{color:'#cfd8e8',font:{size:10},boxWidth:14,padding:8}}},
      onClick:(e,els)=>{
        if(!els || !els.length) return;
        const mi = els[0].index;
        const monthName = MONTH_NAMES[mi];
        // Show actual + target for each entity for the clicked month
        const items = [];
        entities.forEach(en => {
          const actual = (DATA.monthly_actuals[en.monthlyKey]||[])[mi];
          const target = (DATA.monthly_targets[en.monthlyKey]||[])[mi];
          if(actual == null && target == null) return;
          const ach = target>0 && actual!=null ? (actual/target*100).toFixed(1)+'%' : '—';
          items.push({label: en.label + ' — Actual', value: actual, sub: 'Target ' + (target!=null?fmtCur(target):'—') + ' · Ach ' + ach});
        });
        openValueList(`${monthName} — Actual vs Target`, items, {formatter:v=>v!=null?fmtCur(v):'—', hideTotal:true, labelHeader:'Entity'});
      },
      scales:{
        x:{ticks:{color:'#8aa0bf',font:{size:11}}, grid:{color:'rgba(255,255,255,0.04)'}},
        y:{beginAtZero:true, ticks:{color:'#8aa0bf',font:{size:11},callback:v=>fmtShort(v)}, grid:{color:'rgba(255,255,255,0.04)'}}
      }}
  });
}

// ====== Pipeline panel ======
function renderPipelinePanel(){
  const pipe = DATA.pipeline;
  const total = (pipe.by_stage['Finalising Term']||0) + (pipe.by_stage['Objection Handling']||0);
  const ft = pipe.by_stage['Finalising Term']||0;
  const oh = pipe.by_stage['Objection Handling']||0;
  const augClosed = DATA.transactions.filter(t=>t.stage==='Closed Won' && t.month==='September');
  const augTotal = augClosed.reduce((s,t)=>s+t.amount,0);
  const kpis = [
    {label:'Total open pipeline', val:fmtCur(total), sub:pipeTxns.length+' open deals', cls:''},
    {label:'Finalising Term', val:fmtCur(ft), sub:'Close to commitment', cls:'up'},
    {label:'September closed-won', val:fmtCur(augTotal), sub:augClosed.length+' deals · post-YTD', cls:'up'},
    {label:'Brands pipeline', val:fmtCur(pipe.by_unit['Brands']||0), sub:'Owned by Varuni team', cls:''},
    {label:'BD pipeline', val:fmtCur(pipe.by_unit['BD']||0), sub:'New-business unit', cls:''},
    {label:'IT pipeline', val:fmtCur(pipe.by_unit['IT']||0), sub:'IT sales unit', cls:''},
  ];
  if(oh > 0) kpis.splice(2, 0, {label:'Objection Handling', val:fmtCur(oh), sub:'Earlier stage / at-risk', cls:'warn'});
  document.getElementById('pipelineKpis').innerHTML = kpis.map(k=>`
    <div class="card kpi-card">
      <div class="label">${k.label}</div>
      <div class="val">${k.val}</div>
      <div class="delta ${k.cls}">${k.sub}</div>
    </div>`).join('');

  // by unit chart
  const unitLabels = Object.keys(pipe.by_unit);
  const unitVals = unitLabels.map(u=>pipe.by_unit[u]);
  const unitColors = unitLabels.map(u=>COLORS[u==='IT'?'IT':u==='Brands'?'Brands':u==='BD'?'BD':'muted'] || COLORS.muted);
  new Chart(document.getElementById('pipe_unit'),{
    type:'bar',
    data:{labels:unitLabels,
      datasets:[{data:unitVals, backgroundColor:unitColors, borderRadius:4, label:'Pipeline'}]},
    options:{...chartBaseOpts, indexAxis:'y',
      onClick:(e,els)=>{ if(els && els.length){ const u = unitLabels[els[0].index]; openDrillDown(`Pipeline · ${u}`, pipeTxns.filter(t=>t.owner===u)); } },
      plugins:{...chartBaseOpts.plugins, legend:{display:false}},
      scales:{
        x:{ticks:{color:'#8aa0bf',font:{size:11},callback:v=>fmtShort(v)}, grid:{color:'rgba(255,255,255,0.04)'}},
        y:{ticks:{color:'#cfd8e8',font:{size:11}}, grid:{display:false}}
      }}
  });

  // Pipeline by SBU (department) — chart + table
  const deptAgg = {};
  pipeTxns.forEach(t => {
    if(!deptAgg[t.department]) deptAgg[t.department] = {amount:0, count:0};
    deptAgg[t.department].amount += t.amount;
    deptAgg[t.department].count += 1;
  });
  const deptOrder = ['DM','Creative','IT','Corporate'].filter(d => deptAgg[d]);
  const deptLabels = deptOrder.length ? deptOrder : Object.keys(deptAgg);
  const deptVals = deptLabels.map(d => deptAgg[d].amount);
  const deptColors = deptLabels.map(d => COLORS[d] || COLORS.muted);
  const pipeTotalDept = deptVals.reduce((s,v)=>s+v,0);
  new Chart(document.getElementById('pipe_dept'),{
    type:'bar',
    data:{labels: deptLabels,
      datasets:[{data: deptVals, backgroundColor: deptColors, borderRadius:4, label:'Pipeline'}]},
    options:{...chartBaseOpts, indexAxis:'y',
      onClick:(e,els)=>{ if(els && els.length){ const dept = deptLabels[els[0].index]; openDrillDown(`Pipeline · ${dept}`, pipeTxns.filter(t=>t.department===dept)); } },
      plugins:{...chartBaseOpts.plugins, legend:{display:false}},
      scales:{
        x:{ticks:{color:'#8aa0bf',font:{size:11},callback:v=>fmtShort(v)}, grid:{color:'rgba(255,255,255,0.04)'}},
        y:{ticks:{color:'#cfd8e8',font:{size:11}}, grid:{display:false}}
      }}
  });
  const deptTbody = document.querySelector('#pipe_dept_table tbody');
  if(deptTbody){
    deptTbody.innerHTML = deptLabels.map((d,i) => {
      const info = deptAgg[d];
      const avg = info.count>0 ? info.amount/info.count : 0;
      const share = pipeTotalDept>0 ? info.amount/pipeTotalDept : 0;
      const badgeCls = d==='DM'?'dm':d==='Creative'?'crea':d==='Corporate'?'corp':'it';
      return `<tr>
        <td><span class="badge ${badgeCls}">${d}</span></td>
        <td class="num">${fmtCur(info.amount)}</td>
        <td class="num">${info.count}</td>
        <td class="num">${fmtCur(avg)}</td>
        <td class="num">${fmtPct(share)}</td>
      </tr>`;
    }).join('') + `<tr style="border-top:2px solid var(--line);font-weight:600">
      <td>TOTAL</td>
      <td class="num">${fmtCur(pipeTotalDept)}</td>
      <td class="num">${pipeTxns.length}</td>
      <td class="num">${fmtCur(pipeTxns.length>0?pipeTotalDept/pipeTxns.length:0)}</td>
      <td class="num">100.0%</td>
    </tr>`;
  }

  // Pipeline by sales person (manager)
  const mgrAgg = {};
  pipeTxns.forEach(t => {
    const m = t.manager || '—';
    if(!mgrAgg[m]) mgrAgg[m] = {amount:0, count:0};
    mgrAgg[m].amount += t.amount;
    mgrAgg[m].count += 1;
  });
  const mgrSorted = Object.entries(mgrAgg).sort((a,b) => b[1].amount - a[1].amount);
  const mgrLabels = mgrSorted.map(x => x[0]);
  const mgrVals   = mgrSorted.map(x => x[1].amount);
  const mgrPalette = ['#5b8def','#f4b740','#23c285','#a78bfa','#f97373','#22d3ee','#f472b6','#94a3b8'];
  const mgrColors  = mgrLabels.map((_, i) => mgrPalette[i % mgrPalette.length]);
  new Chart(document.getElementById('pipe_mgr'),{
    type:'bar',
    data:{labels: mgrLabels,
      datasets:[{data: mgrVals, backgroundColor: mgrColors, borderRadius:4, label:'Pipeline'}]},
    options:{...chartBaseOpts, indexAxis:'y',
      onClick:(e,els)=>{ if(els && els.length){ const m = mgrLabels[els[0].index]; openDrillDown(`Pipeline · ${m}`, pipeTxns.filter(t => (t.manager||'—') === m)); } },
      plugins:{...chartBaseOpts.plugins, legend:{display:false}},
      scales:{
        x:{ticks:{color:'#8aa0bf',font:{size:11},callback:v=>fmtShort(v)}, grid:{color:'rgba(255,255,255,0.04)'}},
        y:{ticks:{color:'#cfd8e8',font:{size:11}}, grid:{display:false}}
      }}
  });
  const mgrTbody = document.querySelector('#pipe_mgr_table tbody');
  if(mgrTbody){
    const pipeTotalMgr = mgrVals.reduce((s,v) => s + v, 0);
    mgrTbody.innerHTML = mgrSorted.map(([m, info], i) => {
      const avg = info.count > 0 ? info.amount / info.count : 0;
      const share = pipeTotalMgr > 0 ? info.amount / pipeTotalMgr : 0;
      return `<tr>
        <td><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:${mgrColors[i]};margin-right:8px;vertical-align:middle"></span><b>${m}</b></td>
        <td class="num">${fmtCur(info.amount)}</td>
        <td class="num">${info.count}</td>
        <td class="num">${fmtCur(avg)}</td>
        <td class="num">${fmtPct(share)}</td>
      </tr>`;
    }).join('') + `<tr style="border-top:2px solid var(--line);font-weight:600">
      <td>TOTAL</td>
      <td class="num">${fmtCur(pipeTotalMgr)}</td>
      <td class="num">${pipeTxns.length}</td>
      <td class="num">${fmtCur(pipeTxns.length>0?pipeTotalMgr/pipeTxns.length:0)}</td>
      <td class="num">100.0%</td>
    </tr>`;
  }

  // Stage x month stacked
  const monthsP = Array.from(new Set(pipeTxns.map(t=>t.month)));
  const ftData = monthsP.map(m=>pipe.by_month[m]?.['Finalising Term']||0);
  const ohData = monthsP.map(m=>pipe.by_month[m]?.['Objection Handling']||0);
  const stageDatasets = [{label:'Finalising Term', data:ftData, backgroundColor:COLORS.warn, borderRadius:4}];
  if(ohData.some(v=>v>0)) stageDatasets.push({label:'Objection Handling', data:ohData, backgroundColor:COLORS.bad, borderRadius:4});
  new Chart(document.getElementById('pipe_stage'),{
    type:'bar',
    data:{labels:monthsP, datasets: stageDatasets},
    options:{...chartBaseOpts,
      onClick:(e,els)=>{
        if(!els || !els.length) return;
        const el = els[0];
        const stage = stageDatasets[el.datasetIndex].label;
        const month = monthsP[el.index];
        openDrillDown(`${stage} · ${month} — Pipeline deals`, pipeTxns.filter(t=>t.stage===stage && t.month===month));
      },
      scales:{
        x:{stacked:true, ticks:{color:'#8aa0bf'}, grid:{color:'rgba(255,255,255,0.04)'}},
        y:{stacked:true, beginAtZero:true, ticks:{color:'#8aa0bf',callback:v=>fmtShort(v)}, grid:{color:'rgba(255,255,255,0.04)'}}
      }}
  });

  // Pipeline detail table
  const tb = document.querySelector('#pipe_table tbody');
  const sorted = [...pipeTxns].sort((a,b)=>b.amount-a.amount);
  tb.innerHTML = sorted.map(t=>{
    const sbuClass = t.department==='Corporate'?'corp':t.department==='DM'?'dm':t.department==='Creative'?'crea':'it';
    const stageClass = t.stage==='Finalising Term' ? 'pipe-ft' : 'pipe-oh';
    return `<tr>
      <td>${t.month}</td>
      <td><span class="badge ${stageClass}">${t.stage}</span></td>
      <td>${t.owner}</td>
      <td>${t.brand}</td>
      <td>${t.description}</td>
      <td><span class="badge ${sbuClass}">${t.department}</span></td>
      <td>${t.manager||'—'}</td>
      <td class="num">${fmtCur(t.amount)}</td>
    </tr>`;
  }).join('');

  // August closed-won detail table (post-YTD activity)
  const augTb = document.querySelector('#aug_table tbody');
  if(augTb){
    const augSorted = [...augClosed].sort((a,b)=>b.amount-a.amount);
    if(augSorted.length === 0){
      augTb.innerHTML = '<tr><td colspan="6" style="color:var(--muted);text-align:center">No September closed-won deals recorded.</td></tr>';
    } else {
      augTb.innerHTML = augSorted.map(t=>{
        const sbuClass = t.department==='Corporate'?'corp':t.department==='DM'?'dm':t.department==='Creative'?'crea':'it';
        return `<tr>
          <td>${t.owner||'—'}</td>
          <td>${t.brand}</td>
          <td>${t.description}</td>
          <td><span class="badge ${sbuClass}">${t.department}</span></td>
          <td>${t.manager||'—'}</td>
          <td class="num">${fmtCur(t.amount)}</td>
        </tr>`;
      }).join('');
    }
  }
}

// ====== Month-on-month contribution by project type (stacked bar + table) ======
function renderMoMTypeContribution(containerId, txns){
  const host = document.getElementById(containerId);
  if(!host) return;
  const months = ['April','May','June','July','August','September'];
  // Pool of possible categories, ordered for consistent stack sequence
  const CAT_ORDER = ['Retainer','Campaign','Ads','CAG','Creative','IT-Maint','IT-Other','Other'];
  const CAT_COLOR = {
    'Retainer':'#23c285',
    'Campaign':'#5b8def',
    'Ads':'#f4b740',
    'CAG':'#a78bfa',
    'Creative':'#f97373',
    'IT-Maint':'#22d3ee',
    'IT-Other':'#f472b6',
    'Other':'#94a3b8'
  };

  // Compute categories that actually appear in this txn set (preserve CAT_ORDER)
  const catSet = new Set(txns.map(t=>effectiveCategory(t)));
  const cats = CAT_ORDER.filter(c => catSet.has(c));
  // Include any leftover categories not in preset order
  Array.from(catSet).forEach(c => { if(!cats.includes(c)) cats.push(c); });

  // month × cat matrix
  const matrix = {};  // cat -> [Apr, May, Jun, Jul, Aug]
  cats.forEach(c => matrix[c] = months.map(()=>0));
  const monthTotals = months.map(()=>0);
  txns.forEach(t => {
    const mi = months.indexOf(t.month);
    if(mi < 0) return;
    const c = effectiveCategory(t);
    if(!matrix[c]) matrix[c] = months.map(()=>0);
    matrix[c][mi] += t.amount;
    monthTotals[mi] += t.amount;
  });

  // If nothing to show
  if(monthTotals.every(v => v === 0)){
    host.innerHTML = '<div class="card"><p style="color:var(--muted);padding:20px;text-align:center">No closed-won transactions in Apr–Sep.</p></div>';
    return;
  }

  // Render container
  host.innerHTML = `
    <div class="card">
      <h3>Contribution trend by type · Apr–Sep</h3>
      <div class="chart-wrap tall"><canvas id="${containerId}_chart"></canvas></div>
      <div class="note">One line per project-type — makes month-on-month rises and drops easy to read. Click any point to drill into the underlying projects.</div>
    </div>
    <div class="card" style="margin-top:14px;overflow-x:auto">
      <h3>Project type × month (LKR)</h3>
      <table id="${containerId}_table"><thead><tr>
        <th>Project type</th>
        ${months.map(m=>`<th class="num">${m}</th>`).join('')}
        <th class="num">Total</th>
        <th style="width:20%">Share of total</th>
      </tr></thead><tbody></tbody></table>
    </div>
  `;

  // Chart: line per category — easy to spot trends and drops
  const datasets = cats.map(c => {
    const col = CAT_COLOR[c] || COLORS.muted;
    return {
      label: c,
      data: matrix[c],
      borderColor: col,
      backgroundColor: col,
      pointBackgroundColor: col,
      pointBorderColor: col,
      pointRadius: 4,
      pointHoverRadius: 6,
      borderWidth: 2,
      tension: 0.25,
      fill: false,
      spanGaps: true
    };
  });

  new Chart(document.getElementById(containerId+'_chart'),{
    type:'line',
    data:{labels: months, datasets},
    options:{
      responsive:true, maintainAspectRatio:false,
      interaction:{mode:'index', intersect:false},
      plugins:{
        legend:{position:'bottom', labels:{color:'#cfd8e8',font:{size:11},boxWidth:12,padding:10,usePointStyle:true,pointStyle:'line'}},
        tooltip:{
          callbacks:{
            label: ctx => ctx.dataset.label + ': ' + fmtCur(ctx.parsed.y),
            footer: items => 'Month total: ' + fmtCur(monthTotals[items[0].dataIndex])
          }
        }
      },
      onClick: (e, els) => {
        if(!els || els.length === 0) return;
        const el = els[0];
        const cat = datasets[el.datasetIndex].label;
        const month = months[el.index];
        openDrillDown(`${cat} · ${month} — Projects`, txns.filter(t => effectiveCategory(t) === cat && t.month === month));
      },
      scales:{
        x:{ticks:{color:'#8aa0bf',font:{size:11}}, grid:{color:'rgba(255,255,255,0.04)'}},
        y:{beginAtZero:true, ticks:{color:'#8aa0bf',font:{size:11},callback:v=>fmtShort(v)}, grid:{color:'rgba(255,255,255,0.04)'}}
      }
    }
  });

  // Table: type × month + totals + share bar
  const grandTotal = monthTotals.reduce((s,v)=>s+v,0);
  const rowTotals = cats.map(c => matrix[c].reduce((s,v)=>s+v,0));
  const maxRowTotal = Math.max(...rowTotals);
  const rowsHtml = cats.map((c, i) => {
    const total = rowTotals[i];
    const share = grandTotal > 0 ? total / grandTotal : 0;
    return `<tr>
      <td><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:${CAT_COLOR[c] || COLORS.muted};margin-right:8px;vertical-align:middle"></span>${c}</td>
      ${matrix[c].map(v=>`<td class="num" style="color:${v===0?'var(--muted)':'var(--ink)'}">${v===0?'—':fmtCur(v)}</td>`).join('')}
      <td class="num" style="font-weight:600">${fmtCur(total)}</td>
      <td>
        <div class="row-bar">
          <div class="barwrap"><div style="width:${maxRowTotal>0?(total/maxRowTotal*100).toFixed(1):0}%;background:${CAT_COLOR[c] || COLORS.muted}"></div></div>
          <div class="amt">${fmtPct(share)}</div>
        </div>
      </td>
    </tr>`;
  }).join('');
  const totalsRow = `<tr style="border-top:2px solid var(--line);font-weight:600">
    <td>MONTH TOTAL</td>
    ${monthTotals.map(v=>`<td class="num">${fmtCur(v)}</td>`).join('')}
    <td class="num">${fmtCur(grandTotal)}</td>
    <td>—</td>
  </tr>`;
  document.querySelector(`#${containerId}_table tbody`).innerHTML = rowsHtml + totalsRow;
}

// ====== Drill-down modal ======
function openDrillDown(title, txns){
  document.getElementById('drillModalTitle').textContent = title;
  const body = document.getElementById('drillModalBody');
  if(!txns || txns.length === 0){
    body.innerHTML = '<p style="color:var(--muted);padding:20px;text-align:center">No projects in this group.</p>';
  } else {
    const sorted = [...txns].sort((a,b)=>b.amount-a.amount);
    body.innerHTML = '<table><thead><tr><th>Month</th><th>Stage</th><th>Brand</th><th>Project</th><th>Type</th><th>SBU</th><th>Manager</th><th class="num">Amount (LKR)</th></tr></thead><tbody>'
      + sorted.map(t=>{
        const sbuClass = t.department==='Corporate'?'corp':t.department==='DM'?'dm':t.department==='Creative'?'crea':'it';
        return '<tr><td>'+t.month+'</td><td>'+(t.stage||'—')+'</td><td>'+t.brand+'</td><td>'+t.description+'</td><td>'+t.category+'</td><td><span class="badge '+sbuClass+'">'+t.department+'</span></td><td>'+(t.manager||'—')+'</td><td class="num">'+fmtCur(t.amount)+'</td></tr>';
      }).join('')
      + '</tbody></table>';
  }
  const total = (txns||[]).reduce((s,t)=>s+t.amount, 0);
  const n = (txns||[]).length;
  document.getElementById('drillModalCount').textContent = n + ' project' + (n===1?'':'s');
  document.getElementById('drillModalTotal').textContent = 'Total: ' + fmtCur(total);
  document.getElementById('drillModal').classList.add('active');
}
// Generic value-list modal — used for aggregate charts (targets, ROI, retention) where
// there are no per-project rows to drill into. items = [{label, value, sub}] or {label, value} pairs.
function openValueList(title, items, opts){
  opts = opts || {};
  document.getElementById('drillModalTitle').textContent = title;
  const body = document.getElementById('drillModalBody');
  if(!items || items.length === 0){
    body.innerHTML = '<p style="color:var(--muted);padding:20px;text-align:center">No values to display.</p>';
  } else {
    const valueCol = opts.valueLabel || 'Value';
    const isCurrency = opts.currency !== false;
    const fmt = opts.formatter || (isCurrency ? fmtCur : (v => (typeof v === 'number' ? v.toLocaleString() : String(v))));
    body.innerHTML = '<table><thead><tr>'
      + '<th>' + (opts.labelHeader || 'Item') + '</th>'
      + '<th class="num">' + valueCol + '</th>'
      + (items.some(i=>i.sub) ? '<th>Details</th>' : '')
      + '</tr></thead><tbody>'
      + items.map(i => `<tr>
          <td>${i.label}</td>
          <td class="num">${i.value == null ? '—' : fmt(i.value)}</td>
          ${items.some(x=>x.sub) ? `<td style="color:var(--muted);font-size:12px">${i.sub||'—'}</td>` : ''}
        </tr>`).join('')
      + '</tbody></table>';
  }
  const total = opts.total != null ? opts.total : items.reduce((s,i)=> s + (typeof i.value === 'number' ? i.value : 0), 0);
  document.getElementById('drillModalCount').textContent = items.length + ' ' + (opts.itemLabel || 'value') + (items.length === 1 ? '' : 's');
  document.getElementById('drillModalTotal').textContent = opts.hideTotal ? '' : ('Total: ' + (opts.formatter ? opts.formatter(total) : fmtCur(total)));
  document.getElementById('drillModal').classList.add('active');
}

function closeDrillDown(){ document.getElementById('drillModal').classList.remove('active'); }
function setupDrillModal(){
  document.getElementById('drillModalClose').addEventListener('click', closeDrillDown);
  document.getElementById('drillModal').addEventListener('click', e=>{
    if(e.target.id === 'drillModal') closeDrillDown();
  });
  document.addEventListener('keydown', e=>{ if(e.key === 'Escape') closeDrillDown(); });
}
// Sales-person → transactions mapping (uses Closed Won only)
function txnsForPerson(name){
  return closedTxns.filter(t=>t.manager===name);
}

function setupTabs(){
  document.querySelectorAll('.tab').forEach(t=>{
    t.addEventListener('click', ()=>{
      document.querySelectorAll('.tab').forEach(x=>x.classList.remove('active'));
      t.classList.add('active');
      document.querySelectorAll('.panel-section').forEach(p=>p.classList.remove('active'));
      document.getElementById('panel-'+t.dataset.tab).classList.add('active');
      window.scrollTo({top:0,behavior:'smooth'});
    });
  });
}

function renderAll(){
  renderGroupKpis();
  renderSbuTable();
  renderContribTargetChart();
  renderProfitChart();
  renderBrandChartExpandable('g_brand', closedTxns, 30, brand =>
    openDrillDown(`Brand: ${brand} — Projects`, closedTxns.filter(t=>t.brand===brand)));
  renderCatChart('g_cat', closedTxns, cat =>
    openDrillDown(`Project type: ${cat}`, closedTxns.filter(t=>effectiveCategory(t)===cat)));
  renderDeptChart('g_dept', closedTxns, dept =>
    openDrillDown(`${dept} — All projects`, closedTxns.filter(t=>t.department===dept)));
  bindTopFilter('g_top', closedTxns, {baseTitle:'Top 15 billed projects — Group (Apr–Sep)', includeSbu:true});
  renderMoMTypeContribution('g_mom_type', closedTxnsMoM);
  SBU_ORDER.forEach(buildSbuPanel);
  renderSalesUnitPanel();
  renderPipelinePanel();

  renderMonthlyAchievement('group_monthly', [
    {key:'Group',    label:'Group',         color:COLORS.group,  monthlyKey:'Group'},
    {key:'DM',       label:'Digital (DM)',  color:COLORS.DM,     monthlyKey:'DM'},
    {key:'Creative', label:'Creative',      color:COLORS.Creative,monthlyKey:'Creative'},
    {key:'IT',       label:'IT',            color:COLORS.IT,     monthlyKey:'IT'},
  ]);
  renderMonthlyAchievement('sales_monthly', [
    {key:'Brands', label:'Brands', color:COLORS.Brands,   monthlyKey:'Brands'},
    {key:'BD',     label:'BD',     color:COLORS.BD,       monthlyKey:'BD'},
    {key:'IT',     label:'IT',     color:COLORS.IT_sales, monthlyKey:'IT_sales'},
  ]);

  renderQuarterlyPerformance('group_quarterly', [
    {key:'Group',    label:'Group',        color:COLORS.group,    quarterlyTarget: DATA.group.target_quarterly},
    {key:'DM',       label:'Digital (DM)', color:COLORS.DM,       quarterlyTarget: DATA.departments.DM.target_quarterly},
    {key:'Creative', label:'Creative',     color:COLORS.Creative, quarterlyTarget: DATA.departments.Creative.target_quarterly},
    {key:'IT',       label:'IT',           color:COLORS.IT,       quarterlyTarget: DATA.departments.IT.target_quarterly},
  ]);

  renderQuarterlyPerformance('sales_quarterly', [
    {key:'Brands',   label:'Brands', color:COLORS.Brands,   quarterlyTarget: DATA.sales_units.Brands.target_quarterly},
    {key:'BD',       label:'BD',     color:COLORS.BD,       quarterlyTarget: DATA.sales_units.BD.target_quarterly},
    {key:'IT_sales', label:'IT',     color:COLORS.IT_sales, quarterlyTarget: DATA.sales_units.IT.target_quarterly},
  ]);

  if(!TEAM_MODE){
    const hrEntities = [{key:'Group', label:'Group', color:COLORS.group, hrArr: DATA.group_monthly_hr || [], contribArr: DATA.group_monthly_contribution_pl || []}];
    SBU_ORDER.forEach(s => {
      const d = DATA.departments[s];
      if(d.hr_cost != null && d.monthly_hr && d.monthly_contribution){
        hrEntities.push({key:s, label:SBU_LABEL[s], color:COLORS[s], hrArr:d.monthly_hr, contribArr:d.monthly_contribution});
      }
    });
    renderHRRatio('group_hr_ratio', hrEntities);
  }
}

// ===== Period filter =====
function scopeLabel(){
  const list = ALL_MONTHS_LIST.filter(m => ACTIVE_MONTHS.has(m));
  if(list.length === 0) return '—';
  if(list.length === 4 && ['April','May','June','July'].every(m => ACTIVE_MONTHS.has(m))) return 'Apr–Sep (YTD)';
  if(list.length === 1) return list[0];
  return list[0].slice(0,3) + '–' + list[list.length-1].slice(0,3);
}

function destroyAllCharts(){
  document.querySelectorAll('canvas').forEach(c => {
    const chart = Chart.getChart(c);
    if(chart) chart.destroy();
  });
}

function applyPeriodFilter(monthsCsv){
  ACTIVE_MONTHS = new Set(monthsCsv.split(','));
  closedTxns = allClosedTxnsYtd.filter(t => ACTIVE_MONTHS.has(t.month));
  destroyAllCharts();
  renderAll();
  const scopeEl = document.getElementById('filterScope');
  if(scopeEl) scopeEl.textContent = 'Showing: ' + scopeLabel();
}

function setupPeriodFilter(){
  document.querySelectorAll('#periodFilter .filter-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#periodFilter .filter-btn').forEach(x => x.classList.remove('active'));
      btn.classList.add('active');
      applyPeriodFilter(btn.dataset.months);
    });
  });
}

window.addEventListener('DOMContentLoaded', ()=>{
  renderAll();
  setupDrillModal();
  setupTabs();
  setupPeriodFilter();
});
