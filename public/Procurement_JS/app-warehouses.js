  /* ---------- Warehouse dropdown for the Log Delivery ("Deliver To") field ---------- */
  function refreshDeliveryWarehouseOptions(){
    const select = document.getElementById('delivery-warehouse-select');
    if(!select) return;
    const currentValue = select.value || '';
    fetch('/warehouses', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then(res => res.json())
      .then(json => {
        const list = (json && json.data) || [];
        if(!list.length){
          select.innerHTML = '<option value="">No warehouses available</option>';
          return;
        }
        select.innerHTML = '<option value="">Select warehouse...</option>' + list.map(w =>
          `<option value="${htmlEscape(w.name)}"${w.name === currentValue ? ' selected' : ''}>${htmlEscape(w.name)}</option>`
        ).join('');
      })
      .catch(() => {
        select.innerHTML = '<option value="">Unable to load warehouses</option>';
      });
  }
