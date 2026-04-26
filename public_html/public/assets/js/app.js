// public/assets/js/app.js
function currency(n) {
  return Number.parseFloat(n || 0).toFixed(2);
}

function addRow() {
  const tbody = document.querySelector('#itemsTable tbody');
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td>
      <select name="item_type[]" class="form-select" required>
        <option value="hotel">Hotel</option>
        <option value="car">Car</option>
        <option value="sightseeing">Sightseeing</option>
        <option value="other">Other</option>
      </select>
    </td>
    <td><input name="item_title[]" class="form-control" placeholder="e.g., 2N at Hotel ABC" required></td>
    <td><textarea name="item_desc[]" class="form-control" rows="1" placeholder="Room type, inclusions, timings..."></textarea></td>
    <td><input type="number" step="0.01" name="item_qty[]" class="form-control text-end" value="1" oninput="recalcTotals()"></td>
    <td><input name="item_unit[]" class="form-control" value="unit"></td>
    <td><input type="number" step="0.01" name="item_price[]" class="form-control text-end" value="0" oninput="recalcTotals()"></td>
    <td><input type="number" step="0.01" name="item_tax[]" class="form-control text-end" value="0" oninput="recalcTotals()"></td>
    <td class="text-end fw-semibold line-total">0.00</td>
    <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)">×</button></td>
  `;
  tbody.appendChild(tr);
  recalcTotals();
}

function removeRow(btn) {
  const tr = btn.closest('tr');
  tr.remove();
  recalcTotals();
}

function recalcTotals() {
  const rows = document.querySelectorAll('#itemsTable tbody tr');
  let subtotal = 0, tax_total = 0, grand = 0;
  rows.forEach(tr => {
    const qty = parseFloat(tr.querySelector('[name="item_qty[]"]').value || 0);
    const price = parseFloat(tr.querySelector('[name="item_price[]"]').value || 0);
    const taxp = parseFloat(tr.querySelector('[name="item_tax[]"]').value || 0);
    const base = qty * price;
    const tax = base * (taxp/100);
    const line = base + tax;
    subtotal += base;
    tax_total += tax;
    grand += line;
    tr.querySelector('.line-total').textContent = currency(line);
  });
  const discount = parseFloat(document.getElementById('discount').value || 0);
  document.getElementById('subtotal').value = currency(subtotal);
  document.getElementById('tax_total').value = currency(tax_total);
  document.getElementById('grand_total').value = currency(grand - discount);
}
