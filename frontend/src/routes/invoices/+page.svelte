<script>
	import { onMount } from "svelte";
	import { api } from "$lib/api.js";
	import { openInvoicePrintWindow } from "$lib/invoicePrint.js";
	import Nav from "$lib/Nav.svelte";
	import SkeletonTable from "$lib/SkeletonTable.svelte";
	import DateInput from "$lib/DateInput.svelte";
	import ConfirmDialog from "$lib/ConfirmDialog.svelte";
	import { formatDate } from "$lib/format.js";
	import { user } from "$lib/stores/auth.js";
	import WasteSaleDialog from "$lib/WasteSaleDialog.svelte";

	// =========================
	// State
	// =========================

	let invoices = $state([]);
	let loading = $state(true);
	let errorMessage = $state("");
	let search = $state("");
	let statusFilter = $state("");

	let openMenuId = $state(null);
	let actionMenuPosition = $state({ top: 0, left: 0 });
	let exportingId = $state(null);
	let paymentInvoice = $state(null);
	let paymentMethod = $state("cash");
	let paymentDate = $state("");
	let paymentSaving = $state(false);
	let paymentError = $state("");
	let unpaidInvoice = $state(null);
	let unpaidRemark = $state("");
	let unpaidSaving = $state(false);
	let unpaidError = $state("");
	let selectedInvoice = $state(null);
	let deletingInvoice = $state(false);
	let deleteError = $state("");
	let saleInvoice = $state(null);
	let isAdmin = $derived($user?.role === "admin");
	let menuInvoice = $derived(
		invoices.find((invoice) => invoice.id === openMenuId) ?? null,
	);

	let currentPage = $state(1);
	let lastPage = $state(1);
	let totalInvoices = $state(0);

	const statusOptions = [
		{ value: "", label: "All statuses" },
		{ value: "paid", label: "Paid" },
		{ value: "unpaid", label: "Unpaid" },
	];

	let selectedStatusLabel = $derived(
		statusOptions.find((option) => option.value === statusFilter)?.label ??
			"All statuses",
	);

	function selectStatusFilter(value, event) {
		statusFilter = value;
		event.currentTarget.closest("details")?.removeAttribute("open");
		loadInvoices(1);
	}

	function requestInvoiceDeletion(invoice) {
		if (!isAdmin) return;
		selectedInvoice = invoice;
		deleteError = "";
		openMenuId = null;
	}

	function closeDeleteDialog() {
		if (!deletingInvoice) selectedInvoice = null;
	}

	async function deleteInvoice() {
		if (!selectedInvoice || deletingInvoice) return;
		deletingInvoice = true;
		deleteError = "";

		try {
			await api.deleteInvoice(selectedInvoice.id);
			selectedInvoice = null;
			const targetPage = invoices.length === 1 && currentPage > 1
				? currentPage - 1
				: currentPage;
			await loadInvoices(targetPage);
		} catch (error) {
			deleteError = error?.message || "Unable to delete this invoice.";
		} finally {
			deletingInvoice = false;
		}
	}

	function toggleActionMenu(invoiceId, event) {
		if (openMenuId === invoiceId) {
			openMenuId = null;
			return;
		}

		const buttonRect = event.currentTarget.getBoundingClientRect();
		const menuWidth = 190;
		const menuHeight = isAdmin ? 276 : 220;
		const gap = 8;
		const viewportPadding = 8;

		actionMenuPosition = {
			top: Math.min(
				Math.max(buttonRect.top, viewportPadding),
				window.innerHeight - menuHeight - viewportPadding,
			),
			left: Math.max(
				viewportPadding,
				buttonRect.left - menuWidth - gap,
			),
		};
		openMenuId = invoiceId;
	}

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

	function openUnpaidDialog(invoice) {
		unpaidInvoice = invoice;
		unpaidRemark = "";
		unpaidError = "";
		openMenuId = null;
	}

	function closeUnpaidDialog() {
		if (!unpaidSaving) unpaidInvoice = null;
	}

	async function markInvoiceUnpaid() {
		if (!unpaidInvoice || !unpaidRemark.trim() || unpaidSaving) return;
		unpaidSaving = true;
		unpaidError = "";
		try {
			const updated = await api.updateInvoice(unpaidInvoice.id, {
				status: "unpaid",
				unpaid_remark: unpaidRemark.trim(),
			});
			invoices = invoices.map((item) => item.id === updated.id ? updated : item);
			unpaidInvoice = null;
		} catch (error) {
			unpaidError = error?.errors?.unpaid_remark?.[0] || error?.message || "Unable to set invoice as unpaid";
		} finally {
			unpaidSaving = false;
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
		<div class="search-field"><input
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
		</div>

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
		<details class="status-filter">
			<summary class="control" aria-label="Filter invoices by payment status">
				<span>{selectedStatusLabel}</span>
				<span class="select-chevron" aria-hidden="true">⌄</span>
			</summary>
			<div class="status-options">
				{#each statusOptions as option}
					<button
						type="button"
						class:active={statusFilter === option.value}
						onclick={(event) => selectStatusFilter(option.value, event)}
					>
						{option.label}
					</button>
				{/each}
			</div>
		</details>
		<span class="result-count">{totalInvoices} {totalInvoices === 1 ? "result" : "results"}</span>
	</div>

	<!-- =========================
	     INVOICE TABLE
	========================= -->

	<section class="panel invoice-panel">
		{#if loading}
			<SkeletonTable rows={6} columns={7} />
		{:else if errorMessage}
			<p class="error">
				{errorMessage}
			</p>
		{:else if invoices.length === 0}
			<div class="empty-state">
				{#if search.trim() || statusFilter}
					<h3>No matching invoices found</h3>
					<p>Try another invoice number or status.</p>
				{:else}
					<h3>No invoices yet</h3>
					<p>Click Add Invoice to create your first invoice.</p>
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

							<th> Created By </th>

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

								<td>
									{invoice.created_by?.name ?? "—"}
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
										onclick={(event) =>
											toggleActionMenu(invoice.id, event)}
										>
											⋯
										</button>

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

	{#if menuInvoice}
		<div
			class="dropdown-menu floating-action-menu"
			style:--menu-top={`${actionMenuPosition.top}px`}
			style:--menu-left={`${actionMenuPosition.left}px`}
		>
			<a class="menu-link" href={`/invoices/${menuInvoice.id}`}>View Invoice</a>
			<div class="menu-divider"></div>
			<button type="button" disabled={menuInvoice.status === "paid"} onclick={() => openPaymentDialog(menuInvoice)}>Set Paid</button>
			<button type="button" disabled={menuInvoice.status === "unpaid"} onclick={() => openUnpaidDialog(menuInvoice)}>Set Unpaid</button>
			<div class="menu-divider"></div>
			<button type="button" disabled={exportingId === menuInvoice.id} onclick={() => exportPdf(menuInvoice)}>
				{exportingId === menuInvoice.id ? "Preparing..." : "Export PDF"}
			</button>
			<button type="button" onclick={() => { saleInvoice = menuInvoice; openMenuId = null; }}>
				{menuInvoice.waste_sale_amount === null ? "Record Sale" : "Update Sale"}
			</button>
			{#if isAdmin}
				<div class="menu-divider"></div>
				<button type="button" class="delete-action" onclick={() => requestInvoiceDeletion(menuInvoice)}>Delete Invoice</button>
			{/if}
		</div>
	{/if}

	{#if saleInvoice}
		<WasteSaleDialog
			invoice={saleInvoice}
			oncancel={() => (saleInvoice = null)}
			onsaved={(updated) => {
				invoices = invoices.map((item) => item.id === updated.id ? updated : item);
				saleInvoice = null;
			}}
		/>
	{/if}

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
					<DateInput bind:value={paymentDate} required ariaLabel="Select payment date" />
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

	{#if unpaidInvoice}
		<div class="dialog-layer" role="presentation" onclick={(event) => event.currentTarget === event.target && closeUnpaidDialog()}>
			<form class="payment-dialog" onsubmit={(event) => { event.preventDefault(); markInvoiceUnpaid(); }}>
				<h2>Set Invoice Unpaid</h2>
				<p>Explain why {unpaidInvoice.invoice_no} is being changed to unpaid.</p>
				{#if unpaidError}<div class="dialog-error" role="alert">{unpaidError}</div>{/if}
				<label>Remark
					<textarea rows="4" maxlength="1000" bind:value={unpaidRemark} required placeholder="Enter the reason..."></textarea>
				</label>
				<div class="dialog-actions">
					<button type="button" class="btn" onclick={closeUnpaidDialog} disabled={unpaidSaving}>Cancel</button>
					<button type="submit" class="btn btn-primary" disabled={unpaidSaving || !unpaidRemark.trim()}>{unpaidSaving ? "Saving..." : "Confirm Unpaid"}</button>
				</div>
			</form>
		</div>
	{/if}

	<ConfirmDialog
		open={selectedInvoice !== null}
		title="Delete this invoice?"
		message="The invoice will be permanently removed. Any barrel that is no longer linked to an active rental will become available again."
		itemName={selectedInvoice?.invoice_no ?? ""}
		confirmLabel="Delete invoice"
		busy={deletingInvoice}
		errorMessage={deleteError}
		oncancel={closeDeleteDialog}
		onconfirm={deleteInvoice}
	/>
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
		position: relative;
		width: 160px;
		flex: none;
	}

	.status-filter summary {
		display: flex;
		width: 100%;
		align-items: center;
		justify-content: space-between;
		gap: 12px;
		cursor: pointer;
		list-style: none;
		user-select: none;
	}

	.status-filter summary::-webkit-details-marker {
		display: none;
	}

	.select-chevron {
		font-size: 17px;
		line-height: 1;
		transition: transform 0.15s ease;
	}

	.status-filter[open] .select-chevron {
		transform: rotate(180deg);
	}

	.status-options {
		position: absolute;
		top: calc(100% + 6px);
		right: 0;
		left: 0;
		z-index: 1500;
		overflow: hidden;
		border: 1px solid #d7dce5;
		border-radius: 9px;
		background: white;
		box-shadow: 0 12px 28px rgb(15 23 42 / 16%);
	}

	.status-options button {
		display: block;
		width: 100%;
		padding: 10px 13px;
		border: 0;
		background: white;
		color: #202939;
		text-align: left;
		cursor: pointer;
	}

	.status-options button:hover,
	.status-options button:focus-visible,
	.status-options button.active {
		background: #eef5ff;
		color: #1d4ed8;
	}

	/* =========================
	   INVOICE PANEL
	========================= */

	.invoice-panel {
		overflow: hidden;
	}

	/* =========================
	   TABLE
	========================= */

	.table-card {
		position: relative;
		width: 100%;
		max-width: 100%;
		overflow-x: auto;
		overflow-y: hidden;
		overscroll-behavior-inline: contain;
		-webkit-overflow-scrolling: touch;

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

	.floating-action-menu {
		position: fixed;
		top: var(--menu-top);
		right: auto;
		left: var(--menu-left);
		z-index: 2000;
		max-height: calc(100vh - 16px);
		overflow-y: auto;
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

	.dropdown-menu .delete-action {
		color: #c53030;
	}

	.dropdown-menu .delete-action:hover {
		background: #fff1f1;
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
		padding: 28px;
		color: #64748b;
		font-size: 14px;
		text-align: center;
	}

	.empty-state h3 {
		margin: 0 0 6px;
		color: #111827;
	}

	.empty-state p {
		margin: 0;
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
	.payment-dialog select, .payment-dialog textarea { padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; background: white; font: inherit; }
	.payment-dialog textarea { width: 100%; resize: vertical; }
	.dialog-error { margin-bottom: 12px; padding: 10px 12px; border-radius: 8px; background: #fef2f2; color: #b42318; }
	.dialog-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 24px; }

	/* =========================
	   RESPONSIVE
	========================= */

	@media (max-width: 1280px) {
		table {
			min-width: 760px;
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

		th:last-child,
		td:last-child {
			position: sticky;
			right: 0;
			z-index: 2;
			background: white;
			box-shadow: -8px 0 12px -12px rgb(15 23 42 / 45%);
		}

		th:last-child {
			z-index: 3;
			background: #f8fafc;
		}
	}

	@media (max-width: 900px) {
		.toolbar {
			flex-wrap: wrap;
		}

		.dropdown-menu {
			position: fixed;
			top: var(--menu-top);
			right: auto;
			bottom: auto;
			left: var(--menu-left);
			z-index: 2000;
			width: min(190px, calc(100vw - 16px));
			max-height: calc(100vh - 16px);
			overflow-y: auto;
			border-radius: 12px;
			box-shadow: 0 20px 60px rgb(15 23 42 / 28%);
		}
		.search {
			width: 100%;
			max-width: none;
		}

		.status-filter {
			width: 100%;
		}

		.result-count {
			width: auto;
			margin-left: 0;
		}

		.status-column,
		.status-cell {
			width: auto;
		}

		.action-column,
		.action-cell {
			width: 60px;
		}

		th,
		td {
			padding: 12px 10px;
		}
	}

	@media (max-width: 900px) and (max-height: 500px) {
		.toolbar {
			gap: 8px;
			margin-bottom: 12px;
			padding: 8px;
		}

		.search {
			width: min(100%, 250px);
		}

		.table-card {
			max-height: calc(100vh - 190px);
		}
	}
</style>
