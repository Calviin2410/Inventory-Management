<script>
	import { onMount } from "svelte";
	import { api } from "$lib/api.js";
	import Nav from "$lib/Nav.svelte";

	let customers = $state([]);

	let loading = $state(true);
	let errorMessage = $state("");

	let search = $state("");

	let currentPage = $state(1);
	let lastPage = $state(1);
	let totalCustomers = $state(0);

	async function loadCustomers(page = 1) {
		loading = true;
		errorMessage = "";

		try {
			const params = {
				page,
			};

			if (search.trim()) {
				params.search = search.trim();
			}

			const result = await api.getCustomers(params);

			customers = result?.data ?? [];

			currentPage = result?.current_page ?? 1;

			lastPage = result?.last_page ?? 1;

			totalCustomers = result?.total ?? 0;
		} catch (error) {
			errorMessage = error?.message || "Unable to load customers";
		} finally {
			loading = false;
		}
	}

	async function previousPage() {
		if (currentPage <= 1) {
			return;
		}

		await loadCustomers(currentPage - 1);
	}

	async function nextPage() {
		if (currentPage >= lastPage) {
			return;
		}

		await loadCustomers(currentPage + 1);
	}

	onMount(() => {
		loadCustomers(1);
	});
</script>

<Nav />

<main class="app-page">
	<!-- =========================
	     HEADER
	========================= -->

	<header class="page-heading">
		<div>
			<p class="eyebrow">DIRECTORY</p>

			<h1>Customers</h1>

			<p>Customer details collected from your invoices.</p>
		</div>
	</header>

	<!-- =========================
	     TOOLBAR
	========================= -->

	<div class="toolbar">
		<input
			class="control search"
			type="text"
			placeholder="Search name or phone..."
			bind:value={search}
			onkeydown={(event) => {
				if (event.key === "Enter") {
					loadCustomers(1);
				}
			}}
		/>

		<button type="button" class="btn" onclick={() => loadCustomers(1)}>
			Search
		</button>

		<button
			type="button"
			class="btn"
			onclick={() => {
				search = "";
				loadCustomers(1);
			}}
		>
			Refresh
		</button>
	</div>

	<!-- =========================
	     CUSTOMER TABLE
	========================= -->

	<section class="panel">
		{#if loading}
			<div class="state">Loading customers...</div>
		{:else if errorMessage}
			<div class="state error">
				{errorMessage}
			</div>
		{:else if customers.length === 0}
			<div class="state">
				{#if search.trim()}
					<h3>No matching customers found</h3>

					<p>Try another name or phone number.</p>
				{:else}
					<h3>No customers found</h3>

					<p>Customers will appear after an invoice is created.</p>
				{/if}
			</div>
		{:else}
			<div class="table-card">
				<table class="data-table">
					<thead>
						<tr>
							<th> Customer </th>

							<th> Phone </th>

							<th> Record ID </th>
						</tr>
					</thead>

					<tbody>
						{#each customers as customer (customer.id)}
							<tr>
								<td class="strong">
									<span class="avatar">
										{customer.name?.[0]?.toUpperCase() ||
											"W"}
									</span>

									{customer.name || "Walk-in customer"}
								</td>

								<td>
									{customer.phone || "—"}
								</td>

								<td>
									#{customer.id}
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
					Page {currentPage}
					of {lastPage}

					<span>
						{totalCustomers}

						{totalCustomers === 1 ? "customer" : "customers"}
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
	   CUSTOMER
	========================= */

	.strong {
		font-weight: 600;
	}

	.avatar {
		display: inline-grid;
		place-items: center;

		width: 30px;
		height: 30px;

		margin-right: 10px;

		border-radius: 8px;

		background: #eef2ff;
		color: #315ee7;

		font-size: 12px;
		font-weight: 700;
	}

	/* =========================
	   STATES
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
</style>
