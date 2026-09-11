<script>
	import { onMount } from "svelte";
	import { page } from "$app/state";

	import { api } from "$lib/api.js";
	import Nav from "$lib/Nav.svelte";
	import { openInvoicePrintWindow } from "$lib/invoicePrint.js";

	let invoice = $state(null);
	let loading = $state(true);
	let errorMessage = $state("");
	let exporting = $state(false);

	async function loadInvoice() {
		loading = true;
		errorMessage = "";

		try {
			const id = page.params.id;

			console.log("Loading invoice ID:", id);

			invoice = await api.getInvoice(id);

			console.log("Invoice loaded:", invoice);
		} catch (error) {
			console.error("Unable to load invoice:", error);

			if (error?.status === 403) {
				errorMessage =
					"You do not have permission to view this invoice.";
			} else if (error?.status === 404) {
				errorMessage = "Invoice not found.";
			} else {
				errorMessage = error?.message ?? "Unable to load invoice.";
			}
		} finally {
			loading = false;
		}
	}

	async function exportPdf() {
		if (!invoice || exporting) {
			return;
		}

		const printWindow = window.open("", "_blank");

		if (!printWindow) {
			errorMessage = "Please allow pop-ups to export this invoice.";
			return;
		}

		printWindow.document.write(`
		<p style="
			font: 14px sans-serif;
			padding: 24px;
		">
			Preparing invoice...
		</p>
	`);

		exporting = true;
		errorMessage = "";

		try {
			const fullInvoice = await api.getInvoice(invoice.id);

			openInvoicePrintWindow(printWindow, fullInvoice);
		} catch (error) {
			printWindow.close();

			errorMessage =
				error instanceof Error
					? error.message
					: "Unable to export invoice PDF";
		} finally {
			exporting = false;
		}
	}

	onMount(loadInvoice);
</script>

<Nav />

<main class="app-page">
	<div class="page-heading">
		<div>
			<a class="back-link" href="/invoices"> ← Back to invoices </a>

			<h1>
				{invoice?.invoice_no ?? "Invoice"}
			</h1>
		</div>

		{#if invoice}
			<div class="header-actions">
				<button
					type="button"
					class="btn"
					disabled={exporting}
					onclick={exportPdf}
				>
					{exporting ? "Preparing..." : "Export PDF"}
				</button>

				<a
					class="btn btn-primary"
					href={`/invoices/${invoice.id}/edit`}
				>
					Edit Invoice
				</a>
			</div>
		{/if}
	</div>

	{#if loading}
		<div class="message-card">Loading invoice...</div>
	{:else if errorMessage}
		<div class="error">
			{errorMessage}
		</div>
	{:else if invoice}
		<section class="invoice-card">
			<!-- =========================
			     INVOICE DETAILS
			========================= -->

			<div class="detail-grid">
				<div class="detail-item">
					<span class="label"> Invoice Number </span>

					<p class="strong">
						{invoice.invoice_no}
					</p>
				</div>

				<div class="detail-item">
					<span class="label"> Status </span>

					<p>
						<span
							class="status-badge"
							class:paid={invoice.status === "paid"}
							class:unpaid={invoice.status === "unpaid"}
						>
							{invoice.status === "paid" ? "Paid" : "Unpaid"}
						</span>
					</p>
				</div>

				<div class="detail-item">
					<span class="label"> Customer </span>

					<p>
						{invoice.customer?.name ?? "-"}
					</p>
				</div>

				<div class="detail-item">
					<span class="label"> Phone </span>

					<p>
						{invoice.customer?.phone ?? "-"}
					</p>
				</div>

				<div class="detail-item">
					<span class="label"> Issued Date </span>

					<p>
						{invoice.issued_date ?? "-"}
					</p>
				</div>
			</div>

			<!-- =========================
			     ADDRESS
			========================= -->

			<div class="section">
				<span class="label"> Address </span>

				<p>
					{invoice.address ?? "-"}
				</p>
			</div>

			<!-- =========================
			     ITEMS
			========================= -->

			<div class="section">
				<h2>Invoice Items</h2>

				<div class="table-wrapper">
					<table>
						<thead>
							<tr>
								<th> Barrel </th>

								<th> Description </th>

								<th> Rental Start </th>

								<th> Rental End </th>
							</tr>
						</thead>

						<tbody>
							{#each invoice.items ?? [] as item}
								<tr>
									<td class="barrel-code">
										{item.barrel?.code ?? "-"}
									</td>

									<td>
										{item.description ?? "-"}
									</td>

									<td>
										{item.rental_start ?? "-"}
									</td>

									<td>
										{item.rental_end ?? "-"}
									</td>
								</tr>
							{:else}
								<tr>
									<td colspan="4" class="empty">
										No invoice items.
									</td>
								</tr>
							{/each}
						</tbody>
					</table>
				</div>
			</div>
		</section>
	{/if}
</main>

<style>
	.back-link {
		color: #64748b;

		text-decoration: none;

		font-size: 14px;
	}

	.back-link:hover {
		color: #111827;
	}

	/* CARD */

	.invoice-card {
		padding: 24px;

		border: 1px solid #e5e7eb;
		border-radius: 10px;

		background: white;
	}

	/* DETAILS */

	.detail-grid {
		display: grid;

		grid-template-columns: repeat(2, minmax(0, 1fr));

		gap: 24px 40px;
	}

	.detail-item {
		min-width: 0;
	}

	.label {
		display: block;

		margin-bottom: 6px;

		color: #64748b;

		font-size: 13px;
		font-weight: 500;
	}

	.detail-item p,
	.section p {
		margin: 0;

		color: #111827;

		font-size: 14px;

		line-height: 1.6;
	}

	.strong {
		font-weight: 600;
	}

	/* STATUS */

	.status-badge {
		display: inline-flex;
		align-items: center;
		justify-content: center;

		min-width: 64px;

		padding: 5px 10px;

		border-radius: 6px;

		font-size: 13px;
		font-weight: 600;
	}

	.status-badge.paid {
		background: #ccfbf1;
		color: #0f766e;
	}

	.status-badge.unpaid {
		background: #fee2e2;
		color: #dc2626;
	}

	/* SECTIONS */

	.section {
		margin-top: 32px;
		padding-top: 24px;

		border-top: 1px solid #e5e7eb;
	}

	.section h2 {
		margin: 0 0 16px;

		font-size: 18px;
		font-weight: 700;
	}

	/* TABLE */

	.table-wrapper {
		overflow-x: auto;

		border: 1px solid #e5e7eb;
		border-radius: 8px;
	}

	table {
		width: 100%;

		border-collapse: collapse;
	}

	th {
		padding: 13px 15px;

		border-bottom: 1px solid #e5e7eb;

		background: #f8fafc;

		text-align: left;

		color: #64748b;

		font-size: 13px;
		font-weight: 600;
	}

	td {
		padding: 15px;

		border-bottom: 1px solid #e5e7eb;

		color: #111827;

		font-size: 14px;
	}

	tbody tr:last-child td {
		border-bottom: none;
	}

	.barrel-code {
		font-weight: 600;
	}

	.empty {
		padding: 28px;

		text-align: center;

		color: #64748b;
	}

	/* MESSAGES */

	.message-card {
		padding: 22px;

		border: 1px solid #e5e7eb;
		border-radius: 8px;

		background: white;

		color: #64748b;
	}

	.error {
		padding: 13px 15px;

		border: 1px solid #fecaca;
		border-radius: 8px;

		background: #fef2f2;

		color: #b42318;

		font-size: 14px;
	}

	.header-actions {
		display: flex;
		align-items: center;
		gap: 10px;
	}

	/* MOBILE */

	@media (max-width: 700px) {
		.detail-grid {
			grid-template-columns: 1fr;
		}
	}
</style>
