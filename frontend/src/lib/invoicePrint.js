function escapeHtml(value) {
	return String(value ?? '')
		.replaceAll('&', '&amp;')
		.replaceAll('<', '&lt;')
		.replaceAll('>', '&gt;')
		.replaceAll('"', '&quot;')
		.replaceAll("'", '&#039;');
}

function displayDate(value) {
	if (!value) return '—';

	const date = new Date(`${value}T00:00:00`);
	return Number.isNaN(date.getTime())
		? escapeHtml(value)
		: new Intl.DateTimeFormat('en-MY', { day: '2-digit', month: 'short', year: 'numeric' }).format(date);
}

function displayMoney(value) {
	const amount = Number(value ?? 0);
	return new Intl.NumberFormat('en-MY', {
		style: 'currency',
		currency: 'MYR',
		minimumFractionDigits: 2
	}).format(Number.isFinite(amount) ? amount : 0);
}

export function buildInvoiceDocument(invoice) {
	const items = Array.isArray(invoice.items) ? invoice.items : [];
	const rows = items.map((item, index) => `
		<tr>
			<td>${index + 1}</td>
			<td><strong>${escapeHtml(item.barrel?.code || '—')}</strong><br><span>${escapeHtml(item.barrel?.type || '')}</span></td>
			<td>${escapeHtml(item.description || 'Barrel rental')}</td>
			<td>${displayDate(item.rental_start)}</td>
			<td>${displayDate(item.rental_end)}</td>
		</tr>`).join('');

	return `<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>${escapeHtml(invoice.invoice_no || 'Invoice')}</title>
	<style>
		@page { size: A4; margin: 16mm; }
		* { box-sizing: border-box; }
		body { margin: 0; color: #172033; font: 13px/1.5 Arial, sans-serif; }
		.header { display: flex; justify-content: space-between; gap: 32px; padding-bottom: 24px; border-bottom: 2px solid #315ee7; }
		h1 { margin: 0 0 4px; font-size: 30px; letter-spacing: -.04em; }
		.invoice-no { color: #315ee7; font-size: 18px; font-weight: 700; text-align: right; }
		.muted, td span { color: #697386; }
		.meta { display: grid; grid-template-columns: 1fr 1fr; gap: 28px; margin: 28px 0; }
		.meta h2 { margin: 0 0 7px; color: #7a8496; font-size: 10px; letter-spacing: .12em; text-transform: uppercase; }
		.meta p { margin: 2px 0; white-space: pre-line; }
		table { width: 100%; border-collapse: collapse; }
		th { padding: 10px 8px; background: #f3f6fb; color: #596579; font-size: 10px; letter-spacing: .05em; text-align: left; text-transform: uppercase; }
		td { padding: 12px 8px; border-bottom: 1px solid #e4e9f1; vertical-align: top; }
		.summary { width: 280px; margin: 26px 0 0 auto; }
		.summary div { display: flex; justify-content: space-between; padding: 9px 0; border-bottom: 1px solid #e4e9f1; }
		.summary .total { border-bottom: 0; font-size: 17px; font-weight: 700; }
		.notes { margin-top: 32px; padding: 14px 16px; background: #f7f9fc; border-radius: 8px; white-space: pre-line; }
		.footer { margin-top: 48px; color: #8a94a6; font-size: 10px; text-align: center; }
		@media print { .no-print { display: none; } }
	</style>
</head>
<body>
	<header class="header">
		<div><h1>INVOICE</h1><div class="muted">Barrel rental statement</div></div>
		<div><div class="invoice-no">${escapeHtml(invoice.invoice_no || '—')}</div><div class="muted">Issued ${displayDate(invoice.issued_date)}</div></div>
	</header>
	<section class="meta">
		<div><h2>Bill to</h2><p><strong>${escapeHtml(invoice.customer?.name || 'Walk-in customer')}</strong></p><p>${escapeHtml(invoice.customer?.phone || '')}</p><p>${escapeHtml(invoice.address || '')}</p></div>
		<div><h2>Payment</h2><p>Status: <strong>${escapeHtml(String(invoice.status || 'unpaid').toUpperCase())}</strong></p><p>Invoice date: ${displayDate(invoice.issued_date)}</p></div>
	</section>
	<table>
		<thead><tr><th>#</th><th>Barrel</th><th>Description</th><th>Rental start</th><th>Rental end</th></tr></thead>
		<tbody>${rows || '<tr><td colspan="5">No rental items</td></tr>'}</tbody>
	</table>
	<div class="summary"><div class="total"><span>Total</span><strong>${displayMoney(invoice.total_amount)}</strong></div></div>
	${invoice.notes ? `<div class="notes"><strong>Notes</strong><br>${escapeHtml(invoice.notes)}</div>` : ''}
	<div class="footer">Generated from Inventory Management</div>
	<script>window.addEventListener('load', () => setTimeout(() => window.print(), 150));<\/script>
</body>
</html>`;
}

export function openInvoicePrintWindow(printWindow, invoice) {
	printWindow.opener = null;
	printWindow.document.open();
	printWindow.document.write(buildInvoiceDocument(invoice));
	printWindow.document.close();
}