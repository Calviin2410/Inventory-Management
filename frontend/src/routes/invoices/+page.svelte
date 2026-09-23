<script>
	import { onMount } from "svelte";
	import { api } from "$lib/api.js";
	import { openInvoicePrintWindow } from "$lib/invoicePrint.js";
	import Nav from "$lib/Nav.svelte";
	import SkeletonTable from "$lib/SkeletonTable.svelte";
	import { formatDate } from "$lib/format.js";

	// =========================
	// State
	// =========================

	let invoices = $state([]);
	let loading = $state(true);
	let errorMessage = $state("");
	let search = $state("");
	let statusFilter = $state("");

	let openMenuId = $state(null);
	let exportingId = $state(null);
	let paymentInvoice = $state(null);
	let paymentMethod = $state("cash");
	let paymentDate = $state("");
	let paymentSaving = $state(false);
	let paymentError = $state("");

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

			if (statusFilter) {
				params.status = statusFilter;
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

	function openPaymentDialog(invoice) {
		const now = new Date();
		paymentInvoice = invoice;
		paymentMethod = "cash";
		paymentDate = new Date(now.getTime() - now.getTimezoneOffset() * 60_000)
			.toISOString()
			.slice(0, 10);
		paymentError = "";
		openMenuId = null;
	}

	function closePaymentDialog() {
		if (!paymentSaving) paymentInvoice = null;
	}

	async function markInvoicePaid() {
		if (!paymentInvoice || !paymentMethod || !paymentDate) return;
		paymentSaving = true;
		paymentError = "";

		try {
			const updated = await api.updateInvoice(paymentInvoice.id, {
				status: "paid",
				payment_method: paymentMethod,
				payment_date: paymentDate,
			});
			invoices = invoices.map((item) =>
				item.id === paymentInvoice.id ? updated : item,
			);
			paymentInvoice = null;
		} catch (error) {
			paymentError = error?.message || "Unable to record payment";
		} finally {
			paymentSaving = false;
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
			+ Create invoice
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
		{#if search}
			<button type="button" class="search-clear" aria-label="Clear search" onclick={() => { search = ""; loadInvoices(1); }}>×</button>
		{/if}

		<button type="button" class="btn" onclick={() => loadInvoices(1)}>
			Search
		</button>

		<button
			type="button"
			class="btn refresh-btn"
			onclick={() => {
				search = "";
				statusFilter = "";
				loadInvoices(1);
			}}
		>
			Refresh
		</button>
		<select
			class="control status-filter"
			bind:value={statusFilter}
			onchange={() => loadInvoices(1)}
			aria-label="Filter invoices by payment status"
		>
			<option value="">All statuses</option>
			<option value="paid">Paid</option>
			<option value="unpaid">Unpaid</option>
		</select>
		<span class="result-count">{totalInvoices} {totalInvoices === 1 ? "result" : "results"}</span>
	</div>

	<!-- =========================
	     INVOICE TABLE
	========================= -->

	<section class="panel invoice-panel">
		{#if loading}
			<SkeletonTable rows={6} columns={6} />
		{:else if errorMessage}
			<p class="error">
				{errorMessage}
			</p>
		{:else if invoices.length === 0}
			<div class="empty-state">
				{#if search.trim() || statusFilter}
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

							<th> Barrel Code </th>

							<th> Invoice Date </th>

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

								<td class="barrel-codes">
									{invoice.items
										?.map((item) => item.barrel?.code)
										.filter(Boolean)
										.join(", ") || "—"}
								</td>

								<td>
									{formatDate(invoice.issued_date)}
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
												onclick={() => openPaymentDialog(invoice)}
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

	{#if paymentInvoice}
		<div class="dialog-layer" role="presentation" onclick={(event) => event.currentTarget === event.target && closePaymentDialog()}>
			<form class="payment-dialog" onsubmit={(event) => { event.preventDefault(); markInvoicePaid(); }}>
				<h2>Record Payment</h2>
				<p>Mark {paymentInvoice.invoice_no} as paid.</p>

				{#if paymentError}<div class="dialog-error" role="alert">{paymentError}</div>{/if}

				<label>Payment Method
					<select bind:value={paymentMethod} required>
						<option value="cash">Cash</option>
						<option value="bank_in">Bank In</option>
					</select>
				</label>

				<label>Payment Date
					<input type="date" bind:value={paymentDate} required />
				</label>

				<div class="dialog-actions">
					<button type="button" class="btn" onclick={closePaymentDialog} disabled={paymentSaving}>Cancel</button>
					<button type="submit" class="btn btn-primary" disabled={paymentSaving}>
						{paymentSaving ? "Saving..." : "Confirm Paid"}
					</button>
				</div>
			</form>
		</div>
	{/if}
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

	.status-filter {
		min-width: 145px;
		cursor: pointer;
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

	.dialog-layer {
		position: fixed;
		inset: 0;
		z-index: 100;
		display: grid;
		place-items: center;
		padding: 20px;
		background: rgb(15 23 42 / 45%);
	}

	.payment-dialog {
		width: min(100%, 430px);
		padding: 24px;
		border-radius: 12px;
		background: white;
		box-shadow: 0 24px 70px rgb(15 23 42 / 24%);
	}

	.payment-dialog h2 { margin: 0 0 6px; }
	.payment-dialog > p { margin: 0 0 20px; color: #64748b; }
	.payment-dialog label { display: grid; gap: 7px; margin-top: 16px; font-size: 14px; font-weight: 600; }
	.payment-dialog select,
	.payment-dialog input { padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; background: white; font: inherit; }
	.dialog-error { margin-bottom: 12px; padding: 10px 12px; border-radius: 8px; background: #fef2f2; color: #b42318; }
	.dialog-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 24px; }

	/* =========================
	   RESPONSIVE
	========================= */

	@media (max-width: 760px) {
		.dropdown-menu {
			position: fixed;
			top: auto;
			right: 12px;
			bottom: 12px;
			left: 12px;
			width: auto;
			border-radius: 12px;
			box-shadow: 0 20px 60px rgb(15 23 42 / 28%);
		}
		.table-card {
			overflow-x: auto;
			overscroll-behavior-inline: contain;
			-webkit-overflow-scrolling: touch;
		}

		table {
			min-width: 620px;
		}
		th:first-child,
		td:first-child {
			position: sticky;
			left: 0;
			z-index: 2;
			background: white;
			box-shadow: 8px 0 12px -12px rgb(15 23 42 / 45%);
		}
		th:first-child {
			z-index: 3;
			background: #f8fafc;
		}

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
