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
						margin: 15mm;
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
						font-size: 13px;
						line-height: 1.5;

						-webkit-print-color-adjust: exact;
						print-color-adjust: exact;
					}

					.invoice-page {
						width: 100%;
						max-width: 794px;
						margin: 0 auto;
					}

					.header {
						display: flex;
						align-items: flex-start;
						justify-content: space-between;

						gap: 32px;

						padding-bottom: 24px;

						border-bottom: 2px solid #315ee7;
					}

					.header-left {
						min-width: 0;
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

						font-size: 13px;
					}

					.header-right {
						min-width: 200px;

						text-align: right;
					}

					.invoice-no {
						margin-bottom: 7px;

						color: #315ee7;

						font-size: 20px;
						font-weight: 700;
					}

					.issued-date {
						color: #697386;

						font-size: 12px;
					}

					.meta {
						display: grid;

						grid-template-columns:
							minmax(0, 1fr)
							minmax(0, 1fr);

						gap: 60px;

						padding: 28px 0;
					}

					.meta-block {
						min-width: 0;
					}

					.meta-title {
						margin-bottom: 10px;

						color: #7a8496;

						font-size: 10px;
						font-weight: 700;

						letter-spacing: 0.12em;

						text-transform: uppercase;
					}

					.meta-line {
						margin: 3px 0;

						color: #172033;

						line-height: 1.5;
					}

					.meta-line.address {
						white-space: pre-line;
					}

					.status-badge {
						display: inline-flex;

						align-items: center;
						justify-content: center;

						min-width: 70px;

						padding: 4px 10px;

						border-radius: 999px;

						font-size: 11px;
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

						color: #596579;

						text-align: left;

						font-size: 10px;
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

						font-size: 11px;
					}

					.notes {
						margin-top: 28px;

						padding: 15px 17px;

						border: 1px solid #e4e9f1;
						border-radius: 8px;

						background: #f7f9fc;
					}

					.notes-title {
						margin-bottom: 6px;

						color: #596579;

						font-size: 10px;
						font-weight: 700;

						letter-spacing: 0.08em;

						text-transform: uppercase;
					}

					.notes-text {
						margin: 0;

						white-space: pre-line;
					}

					.footer {
						margin-top: 48px;

						padding-top: 16px;

						border-top: 1px solid #e4e9f1;

						color: #8a94a6;

						text-align: center;

						font-size: 10px;
					}

					@media print {
						.invoice-page {
							max-width: none;
						}
					}
				</style>
			</head>

			<body>
				<div class="invoice-page">
					<header class="header">
						<div class="header-left">
							<h1>
								INVOICE
							</h1>

							<div class="subtitle">
								Barrel rental statement
							</div>
						</div>

						<div class="header-right">
							<div class="invoice-no">
								${escapeHtml(
		invoice?.invoice_no ||
		"—",
	)}
							</div>

							<div class="issued-date">
								Issued
								${displayDate(
		invoice?.issued_date,
	)}
							</div>
						</div>
					</header>

					<section class="meta">
						<div class="meta-block">
							<div class="meta-title">
								Bill To
							</div>

							<div class="meta-line">
								<strong>
									${escapeHtml(
		invoice?.customer?.name ||
		"Walk-in customer",
	)}
								</strong>
							</div>

							${invoice?.customer?.phone
			? `
										<div class="meta-line">
											${escapeHtml(
				invoice.customer.phone,
			)}
										</div>
									`
			: ""
		}

							<div class="meta-line address">
								${escapeHtml(
			invoice?.address ||
			"—",
		)}
							</div>
						</div>

						<div class="meta-block">
							<div class="meta-title">
								Payment
							</div>

							<div class="meta-line">
								Status:

								<span
									class="
										status-badge
										${status === "paid"
			? "status-paid"
			: "status-unpaid"
		}
									"
								>
									${statusText}
								</span>
							</div>

							<div class="meta-line">
								Invoice date:
								${displayDate(
			invoice?.issued_date,
		)}
							</div>
						</div>
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
								</tr>
							</thead>

							<tbody>
								${rows ||
		`
										<tr>
											<td
												colspan="5"
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

					<div class="footer">
						Generated from Inventory Management
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