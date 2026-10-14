<script>
	import { onMount } from "svelte";
	import { api } from "$lib/api.js";
	import Nav from "$lib/Nav.svelte";
	import SkeletonTable from "$lib/SkeletonTable.svelte";
	import { formatDate } from "$lib/format.js";
	import ColumnPicker from "$lib/ColumnPicker.svelte";

	const recentInvoiceColumns = [
		{ key: "invoice", label: "Invoice" },
		{ key: "customer", label: "Customer" },
		{ key: "invoiceDate", label: "Invoice Date" },
		{ key: "status", label: "Payment Status" },
		{ key: "settlementStatus", label: "Settlement Status" },
	];
	let visibleColumns = $state(Object.fromEntries(recentInvoiceColumns.map((column) => [column.key, true])));

	let recentInvoices = $state([]);
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

	async function loadDashboard() {
		loading = true;
		errorMessage = "";

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
			loading = false;
		}
	}

	onMount(loadDashboard);
</script>

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

									{#if visibleColumns.status}<td>
										<span
											class="badge"
											class:paid={invoice.status ===
												"paid"}
											class:unpaid={invoice.status ===
												"unpaid"}
										>
											{invoice.status === "paid"
												? "Paid"
												: "Unpaid"}
										</span>
									</td>{/if}
									{#if visibleColumns.settlementStatus}<td>
										<span
											class="badge"
											class:paid={invoice.settlement_status === "settled"}
											class:unpaid={invoice.settlement_status !== "settled"}
										>
											{invoice.settlement_status === "settled" ? "Settled" : "Unsettled"}
										</span>
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
