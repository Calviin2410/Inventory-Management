function escapeHtml(value) {
	return String(value ?? "")
		.replaceAll("&", "&amp;")
		.replaceAll("<", "&lt;")
		.replaceAll(">", "&gt;")
		.replaceAll('"', "&quot;")
		.replaceAll("'", "&#039;");
}

function displayDate(value) {
	if (!value) return "—";

	const date = new Date(`${value}T00:00:00`);

	return Number.isNaN(date.getTime())
		? escapeHtml(value)
		: new Intl.DateTimeFormat("en-MY", {
			day: "2-digit",
			month: "short",
			year: "numeric",
		}).format(date);
}

function displayAmount(value) {
	return new Intl.NumberFormat("en-MY", {
		style: "currency",
		currency: "MYR",
	}).format(Number(value ?? 0));
}

export function buildInvoiceDocument(invoice) {
	const items = Array.isArray(invoice?.items)
		? invoice.items
		: [];

	const status =
		String(invoice?.status ?? "unpaid").toLowerCase();

	const statusText =
		status === "paid"
			? "PAID"
			: "UNPAID";

	const rows = items
		.map((item, index) => {
			const barrelCode =
				item?.barrel?.code || "—";

			const barrelType =
				item?.barrel?.type || "";

			return `
				<tr>
					<td class="number-cell">
						${index + 1}
					</td>

					<td>
						<strong>
							${escapeHtml(barrelCode)}
						</strong>

						${barrelType
					? `
									<div class="barrel-type">
										${escapeHtml(barrelType)}
									</div>
								`
					: ""
				}
					</td>

					<td>
						${escapeHtml(
					item?.description ||
					"Barrel rental",
				)}
					</td>

					<td>
						${displayDate(
					item?.rental_start,
				)}
					</td>

					<td>
						${displayDate(
					item?.rental_end,
				)}
					</td>

					<td class="amount-cell">
						${escapeHtml(displayAmount(invoice?.total_amount))}
					</td>
				</tr>
			`;
		})
		.join("");

	return `
		<!doctype html>

		<html lang="en">
			<head>
				<meta charset="utf-8">

				<meta
					name="viewport"
					content="width=device-width, initial-scale=1"
				>

				<title>
					${escapeHtml(
		invoice?.invoice_no ||
		"Invoice",
	)}
				</title>

				<style>
					@page {
						size: A4;
						margin: 0;
					}

					* {
						box-sizing: border-box;
					}

					html,
					body {
						margin: 0;
						padding: 0;
						background: #ffffff;
					}

					body {
						color: #172033;
						font-family:
							Arial,
							Helvetica,
							sans-serif;
						font-size: 15px;
						line-height: 1.5;

						-webkit-print-color-adjust: exact;
						print-color-adjust: exact;
					}

					.invoice-page {
						width: 100%;
						max-width: 794px;
						margin: 0 auto;
						padding: 15mm;
					}

					.header {
						display: flex;
						align-items: flex-start;
						justify-content: space-between;
						gap: 10px;
						padding-bottom: 24px;
						border-bottom: 2px solid #315ee7;
					}

					.header-left {
						flex: 1;
						min-width: 0;
					}

					.company-logo-wrap {
						display: flex;
						align-items: center;
						width: 100%;
						max-width: 600px;
						gap: 10px;
					}

					.company-truck {
						display: block;
						width: 168px;
						height: 112px;
						object-fit: contain;
					}

					.company-wordmark {
						display: block;
						width: min(410px, calc(100% - 178px));
						height: auto;
					}

					h1 {
						margin: 0 0 7px;

						color: #111827;

						font-size: 30px;
						font-weight: 700;
						line-height: 1;

						letter-spacing: -0.035em;
					}

					.subtitle {
						color: #697386;

						font-size: 15px;
					}

					.header-right {
						min-width: 90px;

						text-align: right;
					}

					.invoice-no {
						margin-bottom: 7px;
						color: #315ee7;
						font-size: 22px;
						font-weight: 700;
					}

					.issued-date {
						color: #697386;

						font-size: 14px;
					}

					.meta {
						display: grid;

						grid-template-columns:
							minmax(0, 1fr)
							minmax(0, 1fr);

						gap: 72px;

						padding: 34px 0 30px;
					}

					.meta-block {
						display: grid;
						align-content: start;
						gap: 18px;
						min-width: 0;
					}

					.meta-title {
						margin-bottom: 10px;

						color: #111827;

						font-size: 12px;
						font-weight: 700;

						letter-spacing: 0.12em;

						text-transform: uppercase;
					}

					.payment-title {
						margin-top: 16px;
					}

					.meta-line {
						display: grid;
						gap: 4px;
						margin: 0;

						color: #172033;

						line-height: 1.5;
					}

					.meta-label {
						color: #111827;
						font-weight: 700;
					}

					.meta-value {
						color: #172033;
						font-weight: 400;
					}

					.meta-line.address {
						white-space: pre-line;
					}

					.address-block {
						margin-bottom: 24px;
						padding-bottom: 20px;
						border-bottom: 1px solid #e4e9f1;
					}

					.status-badge {
						display: inline-flex;

						align-items: center;
						justify-content: center;

						min-width: 70px;

						padding: 4px 10px;

						border-radius: 999px;

						font-size: 12px;
						font-weight: 700;
					}

					.status-paid {
						background: #ccfbf1;
						color: #0f766e;
					}

					.status-unpaid {
						background: #fee2e2;
						color: #dc2626;
					}

					.table-card {
						overflow: hidden;

						border: 1px solid #e4e9f1;
						border-radius: 8px;
					}

					table {
						width: 100%;

						border-collapse: collapse;
					}

					th {
						padding: 11px 10px;

						border-bottom: 1px solid #e4e9f1;

						background: #f3f6fb;

						color: #111827;

						text-align: left;

						font-size: 12px;
						font-weight: 700;

						letter-spacing: 0.05em;

						text-transform: uppercase;
					}

					td {
						padding: 13px 10px;

						border-bottom: 1px solid #e4e9f1;

						color: #172033;

						vertical-align: top;
					}

					tbody tr:last-child td {
						border-bottom: none;
					}

					.number-cell {
						width: 42px;

						text-align: center;
					}

					.barrel-type {
						margin-top: 2px;

						color: #697386;

						font-size: 13px;
					}

					.notes {
						margin-top: 28px;

						padding: 15px 17px;

						border: 1px solid #e4e9f1;
						border-radius: 8px;

						background: #f7f9fc;
					}

					.payment-details {
						display: grid;
						grid-template-columns: repeat(3, minmax(0, 1fr));
						gap: 24px;
						margin-top: 22px;
						padding: 16px 18px;
						border: 1px solid #e4e9f1;
						border-radius: 8px;
						break-inside: avoid;
						page-break-inside: avoid;
					}

					.payment-label {
						margin-bottom: 4px;
						color: #111827;
						font-size: 11px;
						font-weight: 700;
						letter-spacing: 0.06em;
						text-transform: uppercase;
					}

					.payment-value {
						font-weight: 700;
					}

					.amount-cell {
						font-weight: 700;
						white-space: nowrap;
					}

					.notes-title {
						margin-bottom: 6px;

						color: #596579;

						font-size: 12px;
						font-weight: 700;

						letter-spacing: 0.08em;

						text-transform: uppercase;
					}

					.notes-text {
						margin: 0;

						white-space: pre-line;
					}

					.invoice-bottom {
						margin-top: 40px;
						display: grid;
						grid-template-columns: minmax(0, 1.8fr) minmax(180px, 0.8fr);
						gap: 34px;
						align-items: end;
						break-inside: avoid;
						page-break-inside: avoid;
					}

					.terms {
						min-width: 0;
					}

					.terms-title {
						margin-bottom: 6px;
						color: #172033;
						font-size: 11px;
						font-weight: 700;
						letter-spacing: 0.06em;
						text-transform: uppercase;
					}

					.terms ol {
						margin: 0;
						padding-left: 16px;
						color: #596579;
						font-size: 10px;
						line-height: 1.4;
					}

					.terms li + li {
						margin-top: 3px;
					}

					.signature {
						padding-bottom: 4px;
						text-align: center;
					}

					.signature-line {
						width: 100%;
						margin-bottom: 7px;
						border-top: 1px solid #172033;
					}

					.signature-label {
						color: #172033;
						font-size: 10px;
						font-weight: 700;
					}

					.footer {
						margin-top: 24px;

						padding-top: 16px;

						border-top: 1px solid #e4e9f1;

						color: #8a94a6;

						text-align: center;

						font-size: 11px;
					}

					@media print {
						html,
						body {
							width: 210mm;
						}

						.invoice-page {
							max-width: none;
							padding: 15mm;
						}
					}
				</style>
			</head>

			<body>
				<div class="invoice-page">
					<header class="header">
						<div class="header-left">
							<div class="company-logo-wrap">
								<img class="company-truck" src="/images/tks-truck-transparent.png" alt="">
								<img class="company-wordmark" src="/images/tks-wordmark-contact-transparent.png" alt="TKS Waste Management, Co No. SA0221614-V, H/P 012-989 6221 (TKS) / 010-231 1687 (PENG), Office 03-31677966">
							</div>
						</div>
					</header>

					<section class="meta">
						<div class="meta-block">
							<div class="meta-line">
								<div class="meta-label">Invoice Number</div>
								<div class="meta-value">${escapeHtml(invoice?.invoice_no || "—")}</div>
							</div>

							<div class="meta-line">
								<div class="meta-label">Customer</div>
								<div class="meta-value">${escapeHtml(invoice?.customer?.name || "—")}</div>
							</div>

							<div class="meta-line">
								<div class="meta-label">Phone</div>
								<div class="meta-value">${escapeHtml(invoice?.customer?.phone || "—")}</div>
							</div>
						</div>

						<div class="meta-block">
							<div class="meta-line">
								<div class="meta-label">Issued Date</div>
								<div class="meta-value">${displayDate(invoice?.issued_date)}</div>
							</div>

							<div class="meta-line">
								<div class="meta-label">Salesperson</div>
								<div class="meta-value">${escapeHtml(invoice?.created_by?.name || "—")}</div>
							</div>

							<div class="meta-line">
								<div class="meta-label">Vehicle Plate</div>
								<div class="meta-value">${escapeHtml(invoice?.vehicle?.plate_number || "—")}</div>
							</div>

						</div>
					</section>

					<section class="address-block">
						<div class="meta-title">Address</div>
						<div class="meta-line address">${escapeHtml(invoice?.address || "—")}</div>
					</section>

					<div class="table-card">
						<table>
							<thead>
								<tr>
									<th>#</th>
									<th>Barrel</th>
									<th>Description</th>
									<th>Rental Start</th>
									<th>Rental End</th>
									<th>Total Amount</th>
								</tr>
							</thead>

							<tbody>
								${rows ||
		`
										<tr>
											<td
												colspan="6"
												style="
													text-align: center;
													color: #697386;
													padding: 24px;
												"
											>
												No rental items
											</td>
										</tr>
									`
		}
							</tbody>
						</table>
					</div>

					<section class="payment-details">
						<div>
							<div class="payment-label">Paid By</div>
							<div class="payment-value">${status === "paid" ? invoice?.payment_method === "bank_in" ? "Bank In" : "Cash" : "—"}</div>
						</div>
						<div>
							<div class="payment-label">Payment Date</div>
							<div class="payment-value">${status === "paid" ? displayDate(invoice?.payment_date) : "—"}</div>
						</div>
						<div>
							<div class="payment-label">Status</div>
							<div class="payment-value">
								<span class="status-badge ${status === "paid" ? "status-paid" : "status-unpaid"}">${statusText}</span>
							</div>
						</div>
					</section>

					${invoice?.notes
			? `
								<section class="notes">
									<div class="notes-title">
										Notes
									</div>

									<p class="notes-text">
										${escapeHtml(
				invoice.notes,
			)}
									</p>
								</section>
							`
			: ""
		}

					<div class="invoice-bottom">
						<section class="terms">
							<div class="terms-title">Terms &amp; Conditions</div>
							<ol>
								<li>Each order is valid for one delivery address only. Additional delivery addresses will be subject to extra charges.</li>
								<li>Bin rental/service period is limited to 14 days per order. Additional charges will apply for any period exceeding 14 days.</li>
								<li>Additional charges may apply for overloading, prohibited/undeclared waste, waiting time, or additional disposal.</li>
								<li>Overloaded or unsafe bins may be refused for collection until the issue is rectified.</li>
								<li>Any invoice dispute must be raised within 7 days from the invoice date.</li>
								<li>Delivery and collection are subject to site accessibility, traffic, vehicle availability and disposal facility conditions.</li>
								<li>By accepting the service, the customer agrees to these Terms &amp; Conditions.</li>
							</ol>
						</section>
						<div class="signature">
							<div class="signature-line"></div>
							<div class="signature-label">Authorised Signature</div>
						</div>
					</div>

					<div class="footer">
						Generated by TKS Waste Management
					</div>
				</div>

				<script>
					window.addEventListener(
						"load",
						() => {
							setTimeout(
								() => window.print(),
								180
							);
						}
					);
				<\/script>
			</body>
		</html>
	`;
}

export function openInvoicePrintWindow(
	printWindow,
	invoice
) {
	printWindow.opener = null;

	printWindow.document.open();

	printWindow.document.write(
		buildInvoiceDocument(invoice)
	);

	printWindow.document.close();
}
