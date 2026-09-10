<script>
	import { onMount } from "svelte";
	import { api } from "$lib/api.js";
	import { openInvoicePrintWindow } from "$lib/invoicePrint.js";
	import Nav from "$lib/Nav.svelte";

	// =========================
	// State
	// =========================

	let invoices = $state([]);
	let loading = $state(true);
	let errorMessage = $state("");
	let search = $state("");

	let openMenuId = $state(null);
	let exportingId = $state(null);

	let currentPage = $state(1);
	let lastPage = $state(1);
	let totalInvoices = $state(0);

	// =========================
	// Load Invoices
	// =========================
	async function loadInvoices(page = 1) {
		loading = true;
		errorMessage = "";

		try {
			const params = {
				page,
			};

			if (search.trim()) {
				params.search = search.trim();
			}

			const result = await api.getInvoices(params);

			invoices = result?.data ?? [];

			currentPage = result?.current_page ?? 1;

			lastPage = result?.last_page ?? 1;

			totalInvoices = result?.total ?? 0;

			openMenuId = null;
		} catch (error) {
			errorMessage = error?.message || "Unable to load invoices";
		} finally {
			loading = false;
		}
	}

	async function previousPage() {
		if (currentPage <= 1) {
			return;
		}

		await loadInvoices(currentPage - 1);
	}

	async function nextPage() {
		if (currentPage >= lastPage) {
			return;
		}

		await loadInvoices(currentPage + 1);
	}

	// =========================
	// Update Status
	// =========================

	async function updateInvoiceStatus(invoice, newStatus) {
		errorMessage = "";

		try {
			const updated = await api.updateInvoice(invoice.id, {
				status: newStatus,
			});

			invoices = invoices.map((item) =>
				item.id === invoice.id ? updated : item,
			);

			openMenuId = null;
		} catch (error) {
			errorMessage =
				error instanceof Error
					? error.message
					: "Unable to update invoice status";
		}
	}

	// =========================
	// Export PDF
	// =========================

	async function exportPdf(invoice) {
		const printWindow = window.open("", "_blank");

		if (!printWindow) {
			errorMessage =
				"Please allow pop-ups to export this invoice as PDF.";
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

		exportingId = invoice.id;
		openMenuId = null;
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
			exportingId = null;
		}
	}

	// =========================
	// Page Load
	// =========================

	onMount(loadInvoices);
</script>

<Nav />

<main class="app-page">
	<!-- =========================
	     HEADER
	========================= -->

	<header class="page-heading">
		<div>
			<p class="eyebrow">SALES</p>

			<h1>Invoices</h1>

			<p class="subtitle">Create, review and track rental invoices.</p>
		</div>

		<a class="btn btn-primary" href="/invoices/create">
			+ Create Invoice
		</a>
	</header>

	<!-- =========================
	     TOOLBAR
	========================= -->

	<div class="toolbar">
		<input
			class="control search"
			type="text"
			placeholder="Search invoice or customer..."
			bind:value={search}
			onkeydown={(event) => {
				if (event.key === "Enter") {
					loadInvoices(1);
				}
			}}
		/>

		<button type="button" class="btn" onclick={() => loadInvoices(1)}>
			Search
		</button>

		<button
			type="button"
			class="btn refresh-btn"
			onclick={() => {
				search = "";
				loadInvoices(1);
			}}
		>
			Refresh
		</button>
	</div>

	<!-- =========================
	     INVOICE TABLE
	========================= -->

	<section class="panel invoice-panel">
		{#if loading}
			<p class="loading">Loading invoices...</p>
		{:else if errorMessage}
			<p class="error">
				{errorMessage}
			</p>
		{:else if invoices.length === 0}
			<div class="empty-state">
				{#if search.trim()}
					<p>No matching invoices found.</p>
				{:else}
					<p>No invoices found.</p>

					<a href="/invoices/create"> Create your first invoice </a>
				{/if}
			</div>
		{:else}
			<div class="table-card">
				<table>
					<thead>
						<tr>
							<th> Invoice Number </th>

							<th> Customer </th>

							<th> Date </th>

							<th class="status-column"> Status </th>

							<th class="action-column"></th>
						</tr>
					</thead>

					<tbody>
						{#each invoices as invoice (invoice.id)}
							<tr>
								<td class="invoice-number">
									{invoice.invoice_no}
								</td>

								<td>
									{invoice.customer?.name ?? "-"}
								</td>

								<td>
									{invoice.issued_date ?? "-"}
								</td>

								<!-- =========================
								     STATUS
								========================= -->

								<td class="status-cell">
									<span
										class="status-badge"
										class:paid={invoice.status === "paid"}
										class:unpaid={invoice.status ===
											"unpaid"}
									>
										{invoice.status === "paid"
											? "Paid"
											: "Unpaid"}
									</span>
								</td>

								<!-- =========================
								     ACTION
								========================= -->

								<td class="action-cell">
									<div class="action-menu">
										<button
											type="button"
											class="more-button"
											aria-label="Invoice actions"
											onclick={() => {
												openMenuId =
													openMenuId === invoice.id
														? null
														: invoice.id;
											}}
										>
											⋯
										</button>

										{#if openMenuId === invoice.id}
											<div class="dropdown-menu">
												<a
													class="menu-link"
													href={`/invoices/${invoice.id}`}
												>
													View Invoice
												</a>

												<div class="menu-divider"></div>

												<button
													type="button"
													disabled={invoice.status ===
														"paid"}
													onclick={() =>
														updateInvoiceStatus(
															invoice,
															"paid",
														)}
												>
													Set Paid
												</button>

												<button
													type="button"
													disabled={invoice.status ===
														"unpaid"}
													onclick={() =>
														updateInvoiceStatus(
															invoice,
															"unpaid",
														)}
												>
													Set Unpaid
												</button>

												<div class="menu-divider"></div>

												<button
													type="button"
													disabled={exportingId ===
														invoice.id}
													onclick={() =>
														exportPdf(invoice)}
												>
													{exportingId === invoice.id
														? "Preparing..."
														: "Export PDF"}
												</button>
											</div>
										{/if}
									</div>
								</td>
							</tr>
						{/each}
					</tbody>
				</table>
			</div>

			<!-- =========================
			     PAGINATION
			========================= -->

			<div class="pagination">
				<div class="pagination-info">
					Page {currentPage} of {lastPage}

					<span>
						{totalInvoices}
						{totalInvoices === 1 ? "invoice" : "invoices"}
					</span>
				</div>

				<div class="pagination-actions">
					<button
						type="button"
						onclick={previousPage}
						disabled={currentPage <= 1}
					>
						Previous
					</button>

					<button
						type="button"
						onclick={nextPage}
						disabled={currentPage >= lastPage}
					>
						Next
					</button>
				</div>
			</div>
		{/if}
	</section>
</main>

<style>
	/* =========================
	   INVOICE SEARCH
	========================= */

	.search {
		flex: 1;
		max-width: 500px;
	}

	.refresh-btn {
		flex-shrink: 0;
	}

	/* =========================
	   INVOICE PANEL
	========================= */

	.invoice-panel {
		overflow: visible;
	}

	/* =========================
	   TABLE
	========================= */

	.table-card {
		position: relative;
		overflow: visible;

		border: 1px solid #e5e7eb;
		border-radius: 8px;

		background: white;
	}

	table {
		width: 100%;

		border-collapse: separate;
		border-spacing: 0;
	}

	th {
		padding: 15px 18px;

		border-bottom: 1px solid #e5e7eb;

		background: #f8fafc;

		color: #374151;

		text-align: left;

		font-size: 14px;
		font-weight: 600;
	}

	th:first-child {
		border-top-left-radius: 8px;
	}

	th:last-child {
		border-top-right-radius: 8px;
	}

	td {
		padding: 16px 18px;

		border-bottom: 1px solid #e5e7eb;

		color: #111827;

		font-size: 14px;
	}

	tbody tr:last-child td {
		border-bottom: none;
	}

	tbody tr:hover {
		background: #fafafa;
	}

	.invoice-number {
		font-weight: 600;
	}

	/* =========================
	   STATUS
	========================= */

	.status-column,
	.status-cell {
		width: 150px;
	}

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

	.status-badge.unpaid {
		background: #fee2e2;

		color: #dc2626;
	}

	.status-badge.paid {
		background: #ccfbf1;

		color: #0f766e;
	}

	/* =========================
	   ACTION
	========================= */

	.action-column,
	.action-cell {
		width: 70px;
	}

	.action-cell {
		position: relative;

		text-align: right;
	}

	.action-menu {
		position: relative;

		display: inline-block;
	}

	.more-button {
		display: inline-flex;
		align-items: center;
		justify-content: center;

		width: 38px;
		height: 36px;

		padding: 0;

		border: 1px solid #d7dce5;
		border-radius: 8px;

		background: white;
		color: #536078;

		font-size: 20px;
		font-weight: 700;

		cursor: pointer;

		transition:
			background 0.15s ease,
			border-color 0.15s ease;
	}

	.more-button:hover {
		background: #f5f7fb;

		border-color: #bfc7d4;
	}

	/* =========================
	   DROPDOWN
	========================= */

	.dropdown-menu {
		position: absolute;

		top: 42px;
		right: 0;

		z-index: 1000;

		width: 170px;

		overflow: hidden;

		border: 1px solid #e5e7eb;
		border-radius: 8px;

		background: white;

		box-shadow: 0 10px 30px rgba(15, 23, 42, 0.14);
	}

	.dropdown-menu button,
	.menu-link {
		display: block;

		width: 100%;

		box-sizing: border-box;

		padding: 11px 14px;

		border: none;

		background: white;

		color: #202939;

		text-align: left;
		text-decoration: none;

		font-size: 14px;
		font-weight: 500;

		cursor: pointer;
	}

	.dropdown-menu button:hover,
	.menu-link:hover {
		background: #f3f4f6;
	}

	.dropdown-menu button:disabled {
		background: white;

		color: #9ca3af;

		cursor: not-allowed;
	}

	.menu-divider {
		height: 1px;

		background: #e5e7eb;
	}

	/* =========================
	   STATES
	========================= */

	.loading {
		margin: 0;

		padding: 20px 0;

		color: #64748b;
	}

	.error {
		padding: 12px 14px;

		border: 1px solid #fecaca;
		border-radius: 6px;

		background: #fef2f2;

		color: #dc2626;

		font-size: 14px;
	}

	.empty-state {
		padding: 30px;

		border: 1px solid #e5e7eb;
		border-radius: 8px;

		background: white;

		color: #64748b;
	}

	.empty-state p {
		margin: 0 0 10px;
	}

	.empty-state a {
		color: #2563eb;

		font-weight: 500;

		text-decoration: none;
	}

	.empty-state a:hover {
		text-decoration: underline;
	}

	/* =========================
	   RESPONSIVE
	========================= */

	@media (max-width: 760px) {
		.search {
			width: 100%;
			max-width: none;
		}

		.status-column,
		.status-cell {
			width: auto;
		}

		.action-column,
		.action-cell {
			width: 60px;
		}

		.dropdown-menu {
			width: 150px;
		}

		th,
		td {
			padding: 12px 10px;
		}
	}
</style>
