<script>
	import { onMount } from "svelte";
	import { api } from "$lib/api.js";
	import Nav from "$lib/Nav.svelte";
	import SkeletonTable from "$lib/SkeletonTable.svelte";
	import { formatDate } from "$lib/format.js";
	import ColumnPicker from "$lib/ColumnPicker.svelte";
	import DateInput from "$lib/DateInput.svelte";
	import Toast from "$lib/Toast.svelte";
	import { user } from "$lib/stores/auth.js";

	const recentInvoiceColumns = [
		{ key: "invoice", label: "Invoice" },
		{ key: "customer", label: "Customer" },
		{ key: "invoiceDate", label: "Invoice Date" },
		{ key: "createdBy", label: "Created By" },
		{ key: "status", label: "Payment Status" },
		{ key: "settlementStatus", label: "Settlement Status" },
	];
	let visibleColumns = $state(Object.fromEntries(recentInvoiceColumns.map((column) => [column.key, true])));

	let recentInvoices = $state([]);
	let statusMenu = $state({ invoiceId: null, kind: null, top: 0, left: 0 });
	let statusMenuInvoice = $derived(recentInvoices.find((invoice) => invoice.id === statusMenu.invoiceId) ?? null);
	let paymentInvoice = $state(null);
	let paymentMethod = $state("cash");
	let paymentDate = $state("");
	let paymentError = $state("");
	let paymentSaving = $state(false);
	let unpaidInvoice = $state(null);
	let unpaidRemark = $state("");
	let unpaidError = $state("");
	let unpaidSaving = $state(false);
	let settlementInvoice = $state(null);
	let settlementAction = $state("");
	let settlementRemark = $state("");
	let settlementError = $state("");
	let settlementSaving = $state(false);
	let successMessage = $state("");
	let isAdmin = $derived($user?.role === "admin");
	let invoiceSortDirection = $state("");
	let invoiceDateSortDirection = $state("desc");
	let sortedRecentInvoices = $derived([...recentInvoices].sort((a, b) => {
		const direction = invoiceSortDirection ? invoiceSortDirection : invoiceDateSortDirection;
		const sign = direction === "asc" ? 1 : -1;
		if (invoiceSortDirection) {
			const number = (value) => Number(String(value ?? "").replace(/^TKS|^[A-Z]/, "")) || 0;
			return (number(a.invoice_no) - number(b.invoice_no)) * sign;
		}
		return (new Date(a.issued_date).getTime() - new Date(b.issued_date).getTime()) * sign;
	}));

	function toggleRecentSort(column) {
		if (column === "invoice_no") {
			invoiceSortDirection = invoiceSortDirection === "asc" ? "desc" : "asc";
			invoiceDateSortDirection = "";
		} else {
			invoiceDateSortDirection = invoiceDateSortDirection === "asc" ? "desc" : "asc";
			invoiceSortDirection = "";
		}
	}

	let totalInvoices = $state(0);
	let paidInvoices = $state(0);
	let unpaidInvoices = $state(0);
	let settledInvoices = $state(0);
	let availableBarrels = $state(0);
	let rentedBarrels = $state(0);

	let loading = $state(true);
	let errorMessage = $state("");
	let barrelUtilization = $derived(
		availableBarrels + rentedBarrels > 0
			? Math.round(
					(rentedBarrels / (availableBarrels + rentedBarrels)) * 100,
				)
			: 0,
	);

	async function loadDashboard(showLoading = true) {
		if (showLoading) {
			loading = true;
			errorMessage = "";
		}

		try {
			const result = await api.getDashboardSummary();

			totalInvoices = result?.total_invoices ?? 0;

			paidInvoices = result?.paid_invoices ?? 0;

			unpaidInvoices = result?.unpaid_invoices ?? 0;

			settledInvoices = result?.settled_invoices ?? 0;

			availableBarrels = result?.available_barrels ?? 0;

			rentedBarrels = result?.rented_barrels ?? 0;

			recentInvoices = result?.recent_invoices ?? [];
		} catch (error) {
			errorMessage =
				error instanceof Error
					? error.message
					: "Unable to load dashboard";
		} finally {
			if (showLoading) loading = false;
		}
	}

	function toggleStatusMenu(invoice, kind, event) {
		if (statusMenu.invoiceId === invoice.id && statusMenu.kind === kind) {
			closeStatusMenu();
			return;
		}
		const rect = event.currentTarget.getBoundingClientRect();
		const width = 150;
		const height = 100;
		const padding = 10;
		statusMenu = {
			invoiceId: invoice.id,
			kind,
			top: Math.max(padding, Math.min(rect.bottom + 5, window.innerHeight - height - padding)),
			left: Math.max(padding, Math.min(rect.left, window.innerWidth - width - padding)),
		};
	}

	function closeStatusMenu() {
		statusMenu = { invoiceId: null, kind: null, top: 0, left: 0 };
	}

	function closeStatusMenuOnOutsideClick(event) {
		const target = event.target;
		if (target instanceof Element && target.closest(".dashboard-status-control, .dashboard-status-dropdown")) return;
		closeStatusMenu();
	}

	function todayLocal() {
		const now = new Date();
		return new Date(now.getTime() - now.getTimezoneOffset() * 60_000).toISOString().slice(0, 10);
	}

	function openPaymentDialog(invoice) {
		closeStatusMenu();
		paymentInvoice = invoice;
		paymentMethod = "cash";
		paymentDate = todayLocal();
		paymentError = "";
	}

	async function markInvoicePaid() {
		if (!paymentInvoice || !paymentMethod || !paymentDate || paymentSaving) return;
		paymentSaving = true;
		paymentError = "";
		try {
			await api.updateInvoice(paymentInvoice.id, { status: "paid", payment_method: paymentMethod, payment_date: paymentDate });
			successMessage = `${paymentInvoice.invoice_no} marked as paid.`;
			paymentInvoice = null;
			await loadDashboard(false);
		} catch (error) {
			paymentError = error?.message || "Unable to record payment.";
		} finally {
			paymentSaving = false;
		}
	}

	function openUnpaidDialog(invoice) {
		closeStatusMenu();
		unpaidInvoice = invoice;
		unpaidRemark = "";
		unpaidError = "";
	}

	async function markInvoiceUnpaid() {
		if (!unpaidInvoice || !unpaidRemark.trim() || unpaidSaving) return;
		unpaidSaving = true;
		unpaidError = "";
		try {
			await api.updateInvoice(unpaidInvoice.id, { status: "unpaid", unpaid_remark: unpaidRemark.trim() });
			successMessage = `${unpaidInvoice.invoice_no} marked as unpaid.`;
			unpaidInvoice = null;
			await loadDashboard(false);
		} catch (error) {
			unpaidError = error?.errors?.unpaid_remark?.[0] || error?.message || "Unable to update payment status.";
		} finally {
			unpaidSaving = false;
		}
	}

	function openSettlementDialog(invoice, action) {
		closeStatusMenu();
		settlementInvoice = invoice;
		settlementAction = action;
		settlementRemark = "";
		settlementError = "";
	}

	async function submitSettlementUpdate() {
		if (!settlementInvoice || !settlementRemark.trim() || settlementSaving) return;
		settlementSaving = true;
		settlementError = "";
		try {
			await api.updateSettlement(settlementInvoice.id, { action: settlementAction, remark: settlementRemark.trim() });
			successMessage = settlementAction === "settle" ? `${settlementInvoice.invoice_no} settled.` : `${settlementInvoice.invoice_no} settlement reopened.`;
			settlementInvoice = null;
			await loadDashboard(false);
		} catch (error) {
			settlementError = error?.errors?.remark?.[0] || error?.message || "Unable to update settlement status.";
		} finally {
			settlementSaving = false;
		}
	}

	onMount(loadDashboard);
</script>

<svelte:window onclick={closeStatusMenuOnOutsideClick} />

<Nav />

<main class="app-page">
	<header class="page-heading">
		<div>
			<p class="eyebrow">OVERVIEW</p>
			<h1>Operations dashboard</h1>
			<p>
				Monitor inventory, billing and customer activity from one place.
			</p>
		</div>
		<div class="header-actions">
			<span class="live-indicator"><i></i> Live overview</span>
			<a class="btn btn-primary" href="/invoices/create">+ New invoice</a>
		</div>
	</header>

	{#if loading}
		<div class="dashboard-skeleton">
			<SkeletonTable rows={5} columns={4} />
		</div>
	{:else if errorMessage}
		<div class="state error">
			{errorMessage}
		</div>
	{:else}
		<!-- 统计卡片 -->
		<div class="stat-grid">
			<div class="stat-card blue">
				<div class="stat-top"><span>Total invoices</span><i>▤</i></div>
				<strong>{totalInvoices}</strong>
				<small>All recorded invoices</small>
			</div>
			<div class="stat-card green">
				<div class="stat-top"><span>Paid invoices</span><i>✓</i></div>
				<strong>{paidInvoices}</strong>
				<small>Successfully settled</small>
			</div>
			<div class="stat-card amber">
				<div class="stat-top"><span>Outstanding</span><i>!</i></div>
				<strong>{unpaidInvoices}</strong>
				<small>Awaiting payment</small>
			</div>
			<div class="stat-card">
				<div class="stat-top"><span>Settled invoices</span><i>✓</i></div>
				<strong>{settledInvoices}</strong>
				<small>Invoices settled</small>
			</div>
			<div class="stat-card">
				<div class="stat-top">
					<span>Available barrels</span><i>◇</i>
				</div>
				<strong>{availableBarrels}</strong>
				<small>Ready for allocation</small>
			</div>
			<div class="stat-card">
				<div class="stat-top"><span>Barrels in use</span><i>↗</i></div>
				<strong>{rentedBarrels}</strong>
				<small>{barrelUtilization}% inventory utilization</small>
			</div>
		</div>

		<section class="panel">
			<div class="panel-title">
				<div>
					<h2>Recent Invoices</h2>

					<p>The latest rental invoices created.</p>
				</div>

				<div class="panel-controls">
					<ColumnPicker columns={recentInvoiceColumns} bind:visible={visibleColumns} />
				</div>
			</div>

			{#if recentInvoices.length === 0}
				<div class="state">
					<h3>No invoices yet</h3>

					<p>Create your first invoice to see activity here.</p>
				</div>
			{:else}
				<div class="table-wrapper">
					<table class="data-table">
						<thead>
							<tr>
								{#if visibleColumns.invoice}<th aria-sort={invoiceSortDirection === "asc" ? "ascending" : invoiceSortDirection === "desc" ? "descending" : "none"}><button class="sort-header" type="button" onclick={() => toggleRecentSort("invoice_no")}>Invoice <span aria-hidden="true">{invoiceSortDirection === "asc" ? "↑" : invoiceSortDirection === "desc" ? "↓" : "↕"}</span></button></th>{/if}

								{#if visibleColumns.customer}<th> Customer </th>{/if}

								{#if visibleColumns.invoiceDate}<th aria-sort={invoiceDateSortDirection === "asc" ? "ascending" : invoiceDateSortDirection === "desc" ? "descending" : "none"}><button class="sort-header" type="button" onclick={() => toggleRecentSort("issued_date")}>Invoice Date <span aria-hidden="true">{invoiceDateSortDirection === "asc" ? "↑" : invoiceDateSortDirection === "desc" ? "↓" : "↕"}</span></button></th>{/if}
								{#if visibleColumns.createdBy}<th>Created By</th>{/if}

								{#if visibleColumns.status}<th> Payment Status </th>{/if}
								{#if visibleColumns.settlementStatus}<th> Settlement Status </th>{/if}
							</tr>
						</thead>

						<tbody>
							{#each sortedRecentInvoices as invoice (invoice.id)}
								<tr>
									{#if visibleColumns.invoice}<td class="strong">
										<a
											class="invoice-link"
											href={`/invoices/${invoice.id}`}
										>
											{invoice.invoice_no}
										</a>
									</td>{/if}

									{#if visibleColumns.customer}<td>
										{invoice.customer?.name ?? "-"}
									</td>{/if}

									{#if visibleColumns.invoiceDate}<td>
										{formatDate(invoice.issued_date)}
									</td>{/if}
									{#if visibleColumns.createdBy}<td>{invoice.created_by?.name ?? "—"}</td>{/if}

									{#if visibleColumns.status}<td>
						{#if invoice.status !== "cancelled" && ["admin", "staff", "normal_staff"].includes($user?.role)}
							<button
								class="payment-badge status-action dashboard-status-control"
								class:paid={invoice.status === "paid"}
								type="button"
								aria-expanded={statusMenu.invoiceId === invoice.id && statusMenu.kind === "payment"}
								title="Choose payment status"
								onclick={(event) => toggleStatusMenu(invoice, "payment", event)}
							>
								{invoice.status === "paid" ? "Paid" : "Unpaid"}
								<span class="status-chevron" aria-hidden="true"></span>
							</button>
						{:else}
							<span class="badge" class:paid={invoice.status === "paid"} class:unpaid={invoice.status === "unpaid"}>{invoice.status === "cancelled" ? "Cancelled" : invoice.status === "paid" ? "Paid" : "Unpaid"}</span>
						{/if}
									</td>{/if}
									{#if visibleColumns.settlementStatus}<td>
						{#if isAdmin}
							<button
								class="settlement-badge status-action dashboard-status-control"
								class:settled={invoice.settlement_status === "settled"}
								type="button"
								aria-expanded={statusMenu.invoiceId === invoice.id && statusMenu.kind === "settlement"}
								title="Choose settlement status"
								onclick={(event) => toggleStatusMenu(invoice, "settlement", event)}
							>
								<span class="status-icon" aria-hidden="true">{invoice.settlement_status === "settled" ? "✓" : "×"}</span>
								{invoice.settlement_status === "settled" ? "Settled" : "Unsettled"}
								<span class="status-chevron" aria-hidden="true"></span>
							</button>
						{:else}
							<span class="settlement-badge" class:settled={invoice.settlement_status === "settled"}>
								<span class="status-icon" aria-hidden="true">{invoice.settlement_status === "settled" ? "✓" : "×"}</span>
								{invoice.settlement_status === "settled" ? "Settled" : "Unsettled"}
							</span>
						{/if}
									</td>{/if}
								</tr>
							{/each}
						</tbody>
					</table>
				</div>
			{/if}
		</section>
	{/if}
</main>

{#if statusMenuInvoice}
	<div class="status-dropdown dashboard-status-dropdown" style:--menu-top={`${statusMenu.top}px`} style:--menu-left={`${statusMenu.left}px`}>
		{#if statusMenu.kind === "payment"}
			<button type="button" disabled={statusMenuInvoice.status === "paid"} onclick={() => openPaymentDialog(statusMenuInvoice)}>Paid</button>
			<button type="button" class="unpaid-option" disabled={statusMenuInvoice.status !== "paid"} onclick={() => openUnpaidDialog(statusMenuInvoice)}>Unpaid</button>
		{:else if isAdmin}
			<button type="button" disabled={statusMenuInvoice.settlement_status === "settled"} onclick={() => openSettlementDialog(statusMenuInvoice, "settle")}>Settle</button>
			<button type="button" class="unpaid-option" disabled={statusMenuInvoice.settlement_status !== "settled"} onclick={() => openSettlementDialog(statusMenuInvoice, "reopen")}>Reopen</button>
		{/if}
	</div>
{/if}

{#if paymentInvoice}
	<div class="dialog-layer" role="presentation" onclick={(event) => event.currentTarget === event.target && !paymentSaving && (paymentInvoice = null)}>
		<form class="dashboard-dialog" onsubmit={(event) => { event.preventDefault(); markInvoicePaid(); }}>
			<h2>Record Payment</h2>
			<p>Mark {paymentInvoice.invoice_no} as paid.</p>
			{#if paymentError}<div class="dialog-error" role="alert">{paymentError}</div>{/if}
			<label>Payment Method<select bind:value={paymentMethod} required><option value="cash">Cash</option><option value="bank_in">Bank In</option></select></label>
			<label>Payment Date<DateInput bind:value={paymentDate} required ariaLabel="Select payment date" /></label>
			<div class="dialog-actions"><button class="btn" type="button" onclick={() => !paymentSaving && (paymentInvoice = null)} disabled={paymentSaving}>Cancel</button><button class="btn btn-primary" type="submit" disabled={paymentSaving || !paymentDate}>{paymentSaving ? "Saving…" : "Confirm Paid"}</button></div>
		</form>
	</div>
{/if}

{#if unpaidInvoice}
	<div class="dialog-layer" role="presentation" onclick={(event) => event.currentTarget === event.target && !unpaidSaving && (unpaidInvoice = null)}>
		<form class="dashboard-dialog" onsubmit={(event) => { event.preventDefault(); markInvoiceUnpaid(); }}>
			<h2>Set Invoice Unpaid</h2>
			<p>Explain why {unpaidInvoice.invoice_no} is being changed to unpaid.</p>
			{#if unpaidError}<div class="dialog-error" role="alert">{unpaidError}</div>{/if}
			<label>Remark<textarea rows="4" maxlength="1000" bind:value={unpaidRemark} required placeholder="Enter the reason…"></textarea></label>
			<div class="dialog-actions"><button class="btn" type="button" onclick={() => !unpaidSaving && (unpaidInvoice = null)} disabled={unpaidSaving}>Cancel</button><button class="btn btn-primary" type="submit" disabled={unpaidSaving || !unpaidRemark.trim()}>{unpaidSaving ? "Saving…" : "Confirm Unpaid"}</button></div>
		</form>
	</div>
{/if}

{#if settlementInvoice}
	<div class="dialog-layer" role="presentation" onclick={(event) => event.currentTarget === event.target && !settlementSaving && (settlementInvoice = null)}>
		<form class="dashboard-dialog" onsubmit={(event) => { event.preventDefault(); submitSettlementUpdate(); }}>
			<h2>{settlementAction === "settle" ? "Settle Invoice" : "Reopen"}</h2>
			<p>{settlementInvoice.invoice_no}</p>
			{#if settlementError}<div class="dialog-error" role="alert">{settlementError}</div>{/if}
			<label>Remark<textarea rows="4" maxlength="1000" bind:value={settlementRemark} required placeholder="Enter the reason or settlement note"></textarea></label>
			<div class="dialog-actions"><button class="btn" type="button" onclick={() => !settlementSaving && (settlementInvoice = null)} disabled={settlementSaving}>Cancel</button><button class="btn btn-primary" type="submit" disabled={settlementSaving || !settlementRemark.trim()}>{settlementSaving ? "Saving…" : settlementAction === "settle" ? "Settle" : "Reopen"}</button></div>
		</form>
	</div>
{/if}

<Toast message={successMessage} onclose={() => successMessage = ""} />

<style>
	/* =========================
	   DASHBOARD STATS
	========================= */

	.stat-grid {
		display: grid;
		grid-template-columns: repeat(3, minmax(0, 1fr));
		gap: 14px;
		margin-bottom: 24px;
	}

	.stat-card {
		position: relative;
		overflow: hidden;
		padding: 19px 20px 18px;
		border: 1px solid #e3e8ef;
		border-radius: 10px;
		background: white;
		box-shadow: 0 2px 7px rgb(15 23 42 / 3%);
	}

	.stat-card::before {
		position: absolute;
		inset: 0 auto 0 0;
		width: 3px;
		background: #91a0b5;
		content: "";
	}

	.stat-card strong {
		display: block;
		margin: 13px 0 7px;
		color: #172033;
		font-size: 27px;
		font-weight: 750;
		letter-spacing: -0.035em;
	}
	.stat-card small {
		color: #929bab;
		font-size: 10px;
	}
	.stat-top {
		display: flex;
		align-items: center;
		justify-content: space-between;
		color: #68758a;
		font-size: 11px;
		font-weight: 700;
	}
	.stat-top i {
		display: grid;
		width: 27px;
		height: 27px;
		place-items: center;
		border-radius: 7px;
		background: #f0f3f7;
		color: #68758a;
		font-style: normal;
		font-size: 13px;
	}
	.stat-card.blue::before {
		background: #3565d8;
	}
	.stat-card.blue .stat-top i {
		background: #eaf0ff;
		color: #2853ba;
	}
	.stat-card.green::before {
		background: #20a66b;
	}
	.stat-card.green .stat-top i {
		background: #e5f7ef;
		color: #16855a;
	}
	.stat-card.amber::before {
		background: #e3a225;
	}
	.stat-card.amber .stat-top i {
		background: #fff5dd;
		color: #a86c0d;
	}
	.header-actions {
		display: flex;
		align-items: center;
		gap: 12px;
	}
	.live-indicator {
		display: flex;
		align-items: center;
		gap: 7px;
		color: #758196;
		font-size: 11px;
		font-weight: 600;
	}
	.live-indicator i {
		width: 7px;
		height: 7px;
		border-radius: 50%;
		background: #22a86b;
		box-shadow: 0 0 0 4px #ddf5e9;
	}

	/* =========================
	   RECENT INVOICE PANEL
	========================= */

	.panel-title {
		display: flex;
		align-items: center;
		justify-content: space-between;

		gap: 20px;

		padding: 20px 22px;

		border-bottom: 1px solid #e5e7eb;
	}

	.panel-title h2 {
		margin: 0 0 4px;

		color: #202b40;
		font-size: 15px;
	}

	.panel-title p {
		margin: 0;

		color: #64748b;

		font-size: 13px;
	}

	/* =========================
	   TABLE
	========================= */

	.table-wrapper {
		overflow-x: auto;
	}

	.data-table {
		width: 100%;

		border-collapse: collapse;
	}

	.data-table th {
		padding: 14px 18px;

		background: #f8fafc;

		color: #64748b;

		text-align: left;

		font-size: 13px;
		font-weight: 600;
	}

	.data-table td {
		padding: 15px 18px;

		border-top: 1px solid #e5e7eb;

		font-size: 14px;
	}

	.strong {
		font-weight: 600;
	}

	.invoice-link {
		color: #111827;

		text-decoration: none;
	}

	.invoice-link:hover {
		color: #315ee7;
	}

	/* =========================
	   STATUS BADGE
	========================= */

	.badge {
		display: inline-flex;
		align-items: center;
		justify-content: center;

		min-width: 64px;

		padding: 5px 10px;

		border-radius: 6px;

		font-size: 13px;
		font-weight: 600;
	}

	.badge.paid {
		background: #ccfbf1;

		color: #0f766e;
	}

	.badge.unpaid {
		background: #fee2e2;

		color: #dc2626;
	}
	.payment-badge,
	.settlement-badge {
		display: inline-flex;
		min-width: 66px;
		align-items: center;
		justify-content: center;
		gap: 8px;
		padding: 5px 10px;
		border: 0;
		border-radius: 999px;
		background: #fee2e2;
		color: #c81e3a;
		font-size: 12px;
		font-weight: 700;
		white-space: nowrap;
	}
	.payment-badge.paid {
		background: #ccfbf1;
		color: #0f766e;
	}
	.settlement-badge {
		min-width: 82px;
		border-radius: 6px;
		background: #fff1f2;
		color: #be123c;
	}
	.settlement-badge.settled {
		background: #dff8ee;
		color: #087a55;
	}
	.status-action {
		min-width: 122px;
		box-sizing: border-box;
		justify-content: space-between;
		padding: 9px 12px;
		border: 1px solid #ccd4e0;
		border-radius: 9px;
		background: #fff;
		font-family: inherit;
		text-align: left;
		cursor: pointer;
		box-shadow: 0 1px 2px rgb(15 23 42 / 3%);
		transition: border-color 0.15s ease, box-shadow 0.15s ease;
	}
	.payment-badge.status-action { color: #c81e3a; }
	.payment-badge.status-action.paid { background: #fff; color: #0f766e; }
	.settlement-badge.status-action { min-width: 146px; background: #fff; }
	.settlement-badge.status-action.settled { color: #087a55; }
	.status-action:hover { border-color: #aebbd0; box-shadow: 0 2px 5px rgb(15 23 42 / 7%); }
	.status-action:focus-visible { outline: 3px solid rgb(49 94 231 / 28%); outline-offset: 2px; }
	.status-icon {
		display: grid;
		width: 17px;
		height: 17px;
		flex: none;
		place-items: center;
		border-radius: 50%;
		background: #be123c;
		color: #fff;
		font-size: 12px;
		line-height: 1;
	}
	.settlement-badge.settled .status-icon { background: #087a55; }
	.status-chevron {
		width: 6px;
		height: 6px;
		flex: none;
		margin: -3px 1px 0 0;
		border-right: 1.5px solid currentColor;
		border-bottom: 1.5px solid currentColor;
		opacity: 0.72;
		transform: rotate(45deg);
		transition: transform 0.15s ease;
	}
	.status-action[aria-expanded="true"] .status-chevron { margin-top: 3px; transform: rotate(225deg); }
	.status-dropdown {
		position: fixed;
		top: var(--menu-top);
		left: var(--menu-left);
		z-index: 2000;
		display: grid;
		min-width: 150px;
		overflow: hidden;
		border: 1px solid #e5e7eb;
		border-radius: 9px;
		background: #fff;
		box-shadow: 0 10px 30px rgb(15 23 42 / 16%);
	}
	.status-dropdown button {
		width: 100%;
		padding: 10px 13px;
		border: 0;
		background: #fff;
		color: #202939;
		font: inherit;
		font-size: 13px;
		font-weight: 650;
		text-align: left;
		cursor: pointer;
	}
	.status-dropdown button:hover:not(:disabled) { background: #f3f6fb; }
	.status-dropdown button.unpaid-option { color: #be123c; }
	.status-dropdown button:disabled { color: #98a2b3; cursor: default; }
	.dialog-layer {
		position: fixed;
		inset: 0;
		z-index: 2100;
		display: grid;
		place-items: center;
		padding: 20px;
		background: rgb(15 23 42 / 45%);
	}
	.dashboard-dialog {
		width: min(100%, 440px);
		padding: 25px;
		border-radius: 12px;
		background: #fff;
		box-shadow: 0 24px 70px rgb(15 23 42 / 24%);
	}
	.dashboard-dialog h2 { margin: 0; color: #172033; font-size: 21px; }
	.dashboard-dialog > p { margin: 6px 0 22px; color: #64748b; }
	.dashboard-dialog label { display: grid; gap: 8px; margin-top: 14px; color: #344054; font-size: 13px; font-weight: 700; }
	.dashboard-dialog select,
	.dashboard-dialog textarea {
		box-sizing: border-box;
		width: 100%;
		min-height: 42px;
		padding: 10px 12px;
		border: 1px solid #ccd4e0;
		border-radius: 9px;
		outline: none;
		background: #fff;
		color: #172033;
		font: inherit;
		font-weight: 400;
	}
	.dashboard-dialog textarea { resize: vertical; line-height: 1.5; }
	.dashboard-dialog select:focus,
	.dashboard-dialog textarea:focus { border-color: #315ee7; box-shadow: 0 0 0 3px rgb(49 94 231 / 12%); }
	.dialog-error { margin-bottom: 16px; padding: 10px 12px; border-radius: 8px; background: #fef2f2; color: #b42318; font-size: 12px; }
	.dialog-actions { display: flex; justify-content: flex-end; gap: 9px; margin-top: 22px; }

	/* =========================
	   PAGE STATES
	========================= */

	.state {
		padding: 28px;

		color: #64748b;

		font-size: 14px;
		text-align: center;
	}

	.state h3 {
		margin: 0 0 6px;

		color: #111827;
	}

	.state p {
		margin: 0;
	}

	.error {
		color: #dc2626;
	}

	/* =========================
	   RESPONSIVE
	========================= */

	@media (max-width: 900px) {
		.stat-grid {
			grid-template-columns: repeat(2, minmax(0, 1fr));
		}
	}

	@media (max-width: 600px) {
		/* Keep one horizontal scroller (the panel) instead of nesting it inside
		   a second table scroller, which can trap vertical touch scrolling. */
		.table-wrapper {
			display: contents;
		}

		.stat-grid {
			grid-template-columns: repeat(2, minmax(0, 1fr));
			gap: 10px;
		}
		.stat-card {
			min-height: 116px;
			padding: 13px 14px 12px;
			border-radius: 12px;
		}
		.stat-card strong {
			margin: 10px 0 5px;
			font-size: 23px;
		}
		.stat-top {
			font-size: 10px;
		}
		.stat-top i {
			width: 23px;
			height: 23px;
			font-size: 11px;
		}
		.stat-card small {
			font-size: 9px;
		}
		.panel-title {
			flex-wrap: wrap;
			gap: 12px;
			padding: 16px;
		}
		.panel-title > div:first-child {
			flex: 1 1 100%;
			min-width: 0;
		}
		.panel-controls {
			display: flex;
			width: 100%;
			align-items: center;
			justify-content: space-between;
			gap: 12px;
		}
		.panel-controls :global(.column-picker) {
			flex: none;
			width: auto;
		}
		.panel-controls :global(.column-picker summary) {
			width: auto;
			min-width: 132px;
		}
		.header-actions {
			width: 100%;
			justify-content: space-between;
		}
	}
</style>
