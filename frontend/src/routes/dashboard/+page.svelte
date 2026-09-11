<script>
	import { onMount } from "svelte";
	import { api } from "$lib/api.js";
	import Nav from "$lib/Nav.svelte";

	let recentInvoices = $state([]);

	let totalInvoices = $state(0);
	let paidInvoices = $state(0);
	let unpaidInvoices = $state(0);
	let totalCustomers = $state(0);
	let availableBarrels = $state(0);
	let rentedBarrels = $state(0);

	let loading = $state(true);
	let errorMessage = $state("");

	async function loadDashboard() {
		loading = true;
		errorMessage = "";

		try {
			const result = await api.getDashboardSummary();

			totalInvoices = result?.total_invoices ?? 0;

			paidInvoices = result?.paid_invoices ?? 0;

			unpaidInvoices = result?.unpaid_invoices ?? 0;

			totalCustomers = result?.total_customers ?? 0;

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

			<h1>Dashboard</h1>

			<p>Here is a quick overview of your rental activity.</p>
		</div>
	</header>

	{#if loading}
		<div class="state">Loading dashboard...</div>
	{:else if errorMessage}
		<div class="state error">
			{errorMessage}
		</div>
	{:else}
		<!-- 统计卡片 -->
		<div class="stat-grid">
			<div class="stat-card">
				<span> Total Invoices </span>

				<strong>
					{totalInvoices}
				</strong>
			</div>

			<div class="stat-card">
				<span> Paid Invoices </span>

				<strong>
					{paidInvoices}
				</strong>
			</div>

			<div class="stat-card">
				<span> Unpaid Invoices </span>

				<strong>
					{unpaidInvoices}
				</strong>
			</div>

			<div class="stat-card">
				<span> Total Customers </span>

				<strong>
					{totalCustomers}
				</strong>
			</div>

			<div class="stat-card">
				<span> Available Barrels </span>

				<strong>
					{availableBarrels}
				</strong>
			</div>

			<div class="stat-card">
				<span> Rented Barrels </span>

				<strong>
					{rentedBarrels}
				</strong>
			</div>
		</div>

		<section class="panel">
			<div class="panel-title">
				<div>
					<h2>Recent Invoices</h2>

					<p>The latest rental invoices created.</p>
				</div>

				<a href="/invoices"> View All → </a>
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
								<th> Invoice </th>

								<th> Customer </th>

								<th> Date </th>

								<th> Status </th>
							</tr>
						</thead>

						<tbody>
							{#each recentInvoices as invoice (invoice.id)}
								<tr>
									<td class="strong">
										<a
											class="invoice-link"
											href={`/invoices/${invoice.id}`}
										>
											{invoice.invoice_no}
										</a>
									</td>

									<td>
										{invoice.customer?.name ?? "-"}
									</td>

									<td>
										{invoice.issued_date ?? "-"}
									</td>

									<td>
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
									</td>
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

		gap: 16px;

		margin-bottom: 28px;
	}

	.stat-card {
		padding: 20px;

		border: 1px solid #e5e7eb;
		border-radius: 10px;

		background: white;
	}

	.stat-card span {
		display: block;

		margin-bottom: 10px;

		color: #64748b;

		font-size: 13px;
		font-weight: 500;
	}

	.stat-card strong {
		color: #111827;

		font-size: 28px;
	}

	/* =========================
	   RECENT INVOICE PANEL
	========================= */

	.panel-title {
		display: flex;
		align-items: center;
		justify-content: space-between;

		gap: 20px;

		padding: 18px 20px;

		border-bottom: 1px solid #e5e7eb;
	}

	.panel-title h2 {
		margin: 0 0 4px;

		font-size: 18px;
	}

	.panel-title p {
		margin: 0;

		color: #64748b;

		font-size: 13px;
	}

	.panel-title a {
		color: #315ee7;

		font-size: 14px;
		font-weight: 600;

		text-decoration: none;
	}

	.panel-title a:hover {
		text-decoration: underline;
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
		padding: 24px;

		color: #64748b;

		font-size: 14px;
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
			grid-template-columns: 1fr;
		}
	}
</style>
