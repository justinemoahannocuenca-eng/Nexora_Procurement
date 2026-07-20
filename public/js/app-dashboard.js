  /* ---------- Tabs (filter queue) ---------- */
  function setActiveFilter(filter){
    document.querySelectorAll('#approval-tabs .tab').forEach(t=>{
      t.classList.toggle('active', t.dataset.filter === filter);
    });
    document.querySelectorAll('.queue-row').forEach(row=>{
      const match = filter === 'all' || row.dataset.type === filter;
      row.classList.toggle('filtered-out', !match);
    });
    checkEmpty();
  }
  document.querySelectorAll('#approval-tabs .tab').forEach(tab=>{
    tab.addEventListener('click', ()=> setActiveFilter(tab.dataset.filter));
  });

  function filterQueueByType(type){
    setActiveFilter(type);
    document.getElementById('approval-tabs').scrollIntoView({behavior:'smooth', block:'nearest'});
    const labelMap = { po:'Purchase Orders', req:'Requisitions', inv:'Invoices', all:'all requests' };
    showToast(`Filtered queue to ${labelMap[type]}`, 'info');
  }

  /* ---------- Donut chart: draw-in + hover sync + click filter ---------- */
  function initDonut(){
    const segs = document.querySelectorAll('.donut-seg');
    // draw-in animation: start collapsed, then apply real dasharray/offset
    requestAnimationFrame(()=>{
      setTimeout(()=>{
        segs.forEach(seg=>{
          seg.setAttribute('stroke-dasharray', seg.dataset.dasharray);
          seg.setAttribute('stroke-dashoffset', seg.dataset.dashoffset);
        });
      }, 120);
    });

    const centerVal = document.getElementById('donut-center-val');
    const centerLabel = document.getElementById('donut-center-label');
    const hole = document.getElementById('donut-center');
    const defaultVal = centerVal.textContent;

    function light(type){
      segs.forEach(seg=>{
        const match = seg.dataset.type === type;
        seg.classList.toggle('dim', type && !match);
        seg.classList.toggle('raise', type && match);
      });
      document.querySelectorAll('.legend-row').forEach(row=>{
        row.style.background = (type && row.dataset.type === type) ? 'var(--bg)' : '';
      });
      if(type){
        const seg = document.querySelector(`.donut-seg[data-type="${type}"]`);
        centerVal.textContent = seg.dataset.pct + '%';
        centerLabel.textContent = seg.dataset.label;
        hole.classList.add('active');
      } else {
        centerVal.textContent = defaultVal;
        centerLabel.textContent = 'total';
        hole.classList.remove('active');
      }
    }

    segs.forEach(seg=>{
      seg.addEventListener('mouseenter', ()=> light(seg.dataset.type));
      seg.addEventListener('mouseleave', ()=> light(null));
      seg.addEventListener('click', ()=> filterQueueByType(seg.dataset.type === 'other' ? 'all' : seg.dataset.type));
    });
    document.querySelectorAll('.legend-row').forEach(row=>{
      row.addEventListener('mouseenter', ()=> light(row.dataset.type));
      row.addEventListener('mouseleave', ()=> light(null));
    });
  }

  /* ---------- Report date-range chips ---------- */
  const rangeData = {
    mtd:    { label: 'Jul 1 – Jul 7, 2026',   values: [142,158,149,171,164,189], months:['Feb','Mar','Apr','May','Jun','Jul'] },
    quarter:{ label: 'Apr 1 – Jul 7, 2026',   values: [149,171,164,189,201,214], months:['Apr','May','Jun','Jul','Aug*','Sep*'] },
    ytd:    { label: 'Jan 1 – Jul 7, 2026',   values: [131,138,149,171,164,189,201], months:['Jan','Feb','Mar','Apr','May','Jun','Jul'] },
    custom: { label: 'Choose a custom range', values: [142,158,149,171,164,189], months:['Feb','Mar','Apr','May','Jun','Jul'] }
  };
  document.querySelectorAll('#report-chips .chip').forEach(chip=>{
    chip.addEventListener('click', ()=>{
      document.querySelectorAll('#report-chips .chip').forEach(c=>c.classList.remove('active'));
      chip.classList.add('active');
      const range = rangeData[chip.dataset.range];
      document.getElementById('date-range-label').lastChild.textContent = ' ' + range.label;
      const maxVal = Math.max(...range.values);
      const bars = document.getElementById('spend-bars');
      bars.innerHTML = range.values.map((v,i)=>{
        const isLast = i === range.values.length - 1;
        const h = Math.round((v/maxVal)*170);
        return `<div class="bar-col">
                  <div class="bar-val">$${v}k</div>
                  <div class="bar" style="height:0px;background:${isLast ? 'var(--blue)' : '#c9d8fb'};transition:height .5s ease;" data-h="${h}"></div>
                  <div class="bar-label">${range.months[i]}</div>
                </div>`;
      }).join('');
      requestAnimationFrame(()=>{
        bars.querySelectorAll('.bar').forEach(b => b.style.height = b.dataset.h + 'px');
      });
      showToast(`Showing ${chip.textContent.trim()}`, 'info');
    });
  });

  /* ---------- Generate / Download reports ---------- */
  function handleGenerate(btn){
    const row = btn.closest('.report-row');
    const name = row.dataset.report;
    const original = btn.textContent;
    btn.disabled = true;
    btn.innerHTML = `<span class="spin-icon" style="display:inline-block;">⟳</span> Generating`;
    setTimeout(()=>{
      btn.innerHTML = '✓ Generated';
      const meta = row.querySelector('[data-meta]');
      meta.textContent = 'Last generated Just now';
      showToast(`${name} generated`, 'ok');
      setTimeout(()=>{
        btn.disabled = false;
        btn.textContent = original;
      }, 1400);
    }, 1200);
  }

  function handleDownload(btn){
    const row = btn.closest('.report-row');
    const name = row.dataset.report;
    const format = row.dataset.format.toUpperCase();
    const original = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Downloading…';
    setTimeout(()=>{
      btn.textContent = original;
      btn.disabled = false;
      showToast(`${name}.${row.dataset.format} downloaded`, 'info');
    }, 900);
  }

