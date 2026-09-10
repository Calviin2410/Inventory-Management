<script>
	import { onMount } from "svelte";
	import { api } from "$lib/api.js";
	import Nav from "$lib/Nav.svelte";
	import { user } from "$lib/stores/auth.js";

	// =========================
	// State
	// =========================
	let barrels = $state([]);
	let loading = $state(true);
	let errorMessage = $state("");

	let status = $state("");
	let searchCode = $state("");
	let view = $state("table");

	let openMenuId = $state(null);

	let isAdmin = $derived($user?.role === "admin");

	let currentPage = $state(1);
	let lastPage = $state(1);
	let totalBarrels = $state(0);

	// =========================
	// Load Barrels
	// =========================
	async function loadBarrels(page = 1) {
		loading = true;
		errorMessage = "";

		try {
			const params = {
				page,
			};

			if (status) {
				params.status = status;
			}

			if (searchCode.trim()) {
				params.search = searchCode.trim();
			}

			const result = await api.getBarrels(params);

			barrels = result?.data ?? [];

			currentPage = result?.current_page ?? 1;

			lastPage = result?.last_page ?? 1;

			totalBarrels = result?.total ?? 0;

			openMenuId = null;
		} catch (error) {
			errorMessage = error?.message || "Unable to load barrels";
		} finally {
			loading = false;
		}
	}

	// =========================
	// Update Status
	// =========================
	async function handleStatusUpdate(barrel, newStatus) {
		try {
			/*
				之后如果 backend 已经有 update API，
				这里可以改成：

				await api.updateBarrel(barrel.id, {
					status: newStatus
				});
			*/

			const updated = await api.updateBarrel(barrel.id, {
				status: newStatus,
			});
			barrels = barrels.map((item) =>
				item.id === barrel.id ? updated : item,
			);
			openMenuId = null;
		} catch (error) {
			errorMessage = error?.message || "Unable to update barrel status";
		}
	}

	// =========================
	// Status Counts
	// =========================
	let statusCounts = $derived({
		available: barrels.filter((barrel) => barrel.status === "available")
			.length,

		rented: barrels.filter((barrel) => barrel.status === "rented").length,

		returning: barrels.filter((barrel) => barrel.status === "returning")
			.length,
	});

	async function deleteBarrel(barrel) {
		if (!isAdmin) {
			return;
		}

		if (barrel.status === "rented") {
			alert("A rented barrel cannot be deleted.");

			return;
		}

		const confirmed = confirm(`Delete barrel ${barrel.code}?`);

		if (!confirmed) {
			return;
		}

		try {
			await api.deleteBarrel(barrel.id);

			barrels = barrels.filter((item) => item.id !== barrel.id);

			openMenuId = null;
		} catch (error) {
			console.error(error);

			alert(error?.message ?? "Unable to delete barrel.");
		}
	}

	onMount(loadBarrels);
</script>

<Nav />

<main class="app-page">
	<header class="page-heading">
		<div>
			<p class="eyebrow">RENTAL ASSETS</p>

			<h1>Barrels</h1>

			<p>See availability and current rental status at a glance.</p>
		</div>

		{#if isAdmin}
			<a class="add-button" href="/barrels/create"> + Add Barrel </a>
		{/if}
	</header>

	<!-- =========================
	     TOOLBAR
	========================= -->
	<div class="toolbar">
		<!-- Search -->
		<input
			class="control search"
			type="text"
			placeholder="Search barrel code..."
			bind:value={searchCode}
			onkeydown={(event) => {
				if (event.key === "Enter") {
					loadBarrels(1);
				}
			}}
		/>

		<button type="button" class="btn" onclick={() => loadBarrels(1)}>
			Search
		</button>

		<!-- Status -->
		<select
			class="control"
			bind:value={status}
			onchange={() => loadBarrels(1)}
		>
			<option value=""> All Status </option>

			<option value="available"> Available </option>

			<option value="rented"> Rented </option>

			<option value="returning"> Returning </option>
		</select>

		<!-- View Switch -->
		<div class="view-switch" aria-label="Barrel view">
			<button
				type="button"
				class:active={view === "table"}
				onclick={() => (view = "table")}
			>
				☷ Table
			</button>

			<button
				type="button"
				class:active={view === "visual"}
				onclick={() => (view = "visual")}
			>
				◉ Visual
			</button>
		</div>
	</div>

	{#if view === "visual" && !loading && !errorMessage && barrels.length > 0}
		<div class="legend">
			<span>
				<i class="available"></i>
				Available
				<strong>{statusCounts.available}</strong>
			</span>

			<span>
				<i class="rented"></i>
				Rented
				<strong>{statusCounts.rented}</strong>
			</span>

			<span>
				<i class="returning"></i>
				Returning
				<strong>{statusCounts.returning}</strong>
			</span>
		</div>
	{/if}

	<!-- =========================
	     CONTENT
	========================= -->
	<section class="panel barrels-panel">
		{#if loading}
			<div class="state">Loading barrels…</div>
		{:else if errorMessage}
			<div class="state error">
				{errorMessage}
			</div>
		{:else if barrels.length === 0}
			<div class="state">
				<h3>No barrels found</h3>

				<p>Add a barrel or adjust your filters.</p>
			</div>
		{:else}
			{#if view === "visual"}
				<div class="barrel-grid">
					{#each barrels as barrel (barrel.id)}
						<article class="barrel-card {barrel.status}">
							<div class="barrel-picture" aria-hidden="true">
								<div class="barrel-top"></div>

								<div class="barrel-body">
									<span class="band top"></span>

									<span class="barrel-code">
										{barrel.code}
									</span>

									<span class="band bottom"></span>
								</div>

								<div class="barrel-bottom"></div>
							</div>

							<div class="barrel-info">
								<div>
									<strong>
										Barrel {barrel.code}
									</strong>
								</div>

								<span class="badge {barrel.status}">
									{barrel.status}
								</span>
							</div>

							<dl>
								<div>
									<dt>Customer</dt>

									<dd>
										{barrel.current_customer?.name || "—"}
									</dd>
								</div>

								<div>
									<dt>Invoice</dt>

									<dd>
										{barrel.invoice_no || "—"}
									</dd>
								</div>
							</dl>
						</article>
					{/each}
				</div>
			{:else}
				<div class="table-card">
					<table class="data-table">
						<thead>
							<tr>
								<th> Barrel Code </th>

								<th> Status </th>

								<th> Invoice </th>

								<th> Customer </th>

								<th class="action-column"></th>
							</tr>
						</thead>

						<tbody>
							{#each barrels as barrel (barrel.id)}
								<tr>
									<td>
										<span
											class="barrel-code-badge {barrel.status}"
										>
											{barrel.code}
										</span>
									</td>

									<td>
										<span class="badge {barrel.status}">
											{barrel.status}
										</span>
									</td>

									<td>
										{barrel.invoice_no || "—"}
									</td>

									<td>
										{barrel.current_customer?.name || "—"}
									</td>

									<td class="action-cell">
										<div class="action-menu">
											<button
												type="button"
												class="more-button"
												onclick={() => {
													openMenuId =
														openMenuId === barrel.id
															? null
															: barrel.id;
												}}
											>
												⋯
											</button>

											{#if openMenuId === barrel.id}
												<div class="dropdown-menu">
													<button
														type="button"
														onclick={() =>
															handleStatusUpdate(
																barrel,
																"available",
															)}
													>
														Set Available
													</button>

													<button
														type="button"
														onclick={() =>
															handleStatusUpdate(
																barrel,
																"rented",
															)}
													>
														Set Rented
													</button>

													<button
														type="button"
														onclick={() =>
															handleStatusUpdate(
																barrel,
																"returning",
															)}
													>
														Set Returning
													</button>

													{#if isAdmin}
														<div
															class="menu-divider"
														></div>

														<button
															type="button"
															class="delete-action"
															disabled={barrel.status ===
																"rented"}
															onclick={() =>
																deleteBarrel(
																	barrel,
																)}
														>
															Delete Barrel
														</button>
													{/if}
												</div>
											{/if}
										</div>
									</td>
								</tr>
							{/each}
						</tbody>
					</table>
				</div>
			{/if}

			<!-- =========================
		     PAGINATION
		========================= -->

			<div class="pagination">
				<div class="pagination-info">
					Page {currentPage} of {lastPage}

					<span>
						{totalBarrels}
						{totalBarrels === 1 ? "barrel" : "barrels"}
					</span>
				</div>

				<div class="pagination-actions">
					<button
						type="button"
						disabled={currentPage <= 1}
						onclick={() => loadBarrels(currentPage - 1)}
					>
						Previous
					</button>

					<button
						type="button"
						disabled={currentPage >= lastPage}
						onclick={() => loadBarrels(currentPage + 1)}
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
	   BARREL SEARCH
	========================= */

	.search {
		width: 270px;
	}

	/* =========================
	   ADD BARREL BUTTON
	========================= */

	.add-button {
		display: inline-flex;
		align-items: center;
		justify-content: center;

		gap: 6px;

		padding: 11px 18px;

		border: none;
		border-radius: 8px;

		background: #2563eb;
		color: white;

		font-size: 14px;
		font-weight: 700;

		text-decoration: none;

		cursor: pointer;

		box-shadow: 0 2px 6px rgb(37 99 235 / 20%);

		transition:
			background 0.15s ease,
			transform 0.15s ease;
	}

	.add-button:hover {
		background: #1d4ed8;
	}

	.add-button:active {
		transform: translateY(1px);
	}

	/* =========================
	   VIEW SWITCH
	========================= */

	.view-switch {
		display: flex;

		gap: 4px;

		padding: 3px;

		border: 1px solid #d9e0ea;
		border-radius: 9px;

		background: #f4f6f9;
	}

	.view-switch button {
		display: flex;
		align-items: center;
		justify-content: center;

		gap: 6px;

		min-height: 34px;

		padding: 0 11px;

		border: 0;
		border-radius: 6px;

		background: transparent;
		color: #778195;

		font-size: 12px;
		font-weight: 700;

		cursor: pointer;
	}

	.view-switch button.active {
		background: white;
		color: #315ee7;

		box-shadow: 0 2px 7px rgb(30 52 90 / 10%);
	}

	/* =========================
	   LEGEND
	========================= */

	.legend {
		display: flex;
		justify-content: flex-end;

		gap: 18px;

		margin: -5px 2px 13px;

		color: #697386;

		font-size: 12px;
	}

	.legend span {
		display: flex;
		align-items: center;

		gap: 6px;
	}

	.legend i {
		width: 9px;
		height: 9px;

		border-radius: 50%;
	}

	.legend i.available {
		background: #26a879;
	}

	.legend i.rented {
		background: #e29a35;
	}

	.legend i.returning {
		background: #5577df;
	}

	.legend strong {
		color: #344054;
	}

	/* =========================
	   TABLE CARD
	========================= */

	.table-card {
		position: relative;

		overflow: visible;

		border: 1px solid #e5e7eb;
		border-radius: 8px;

		background: white;
	}

	.data-table {
		width: 100%;

		border-collapse: separate;
		border-spacing: 0;
	}

	.data-table th {
		padding: 15px 18px;

		border-bottom: 1px solid #e5e7eb;

		background: #f8fafc;

		color: #374151;

		text-align: left;

		font-size: 14px;
		font-weight: 600;
	}

	.data-table th:first-child {
		border-top-left-radius: 8px;
	}

	.data-table th:last-child {
		border-top-right-radius: 8px;
	}

	.data-table td {
		padding: 16px 18px;

		border-bottom: 1px solid #e5e7eb;

		color: #111827;

		font-size: 14px;
	}

	.data-table tbody tr:last-child td {
		border-bottom: none;
	}

	.data-table tbody tr:hover {
		background: #fafafa;
	}

	/* =========================
	   BARREL CODE BADGE
	========================= */

	.barrel-code-badge {
		display: inline-flex;
		align-items: center;
		justify-content: center;

		padding: 6px 10px;

		border-radius: 8px;

		font-size: 14px;
		font-weight: 700;
	}

	/* =========================
	   STATUS BADGE
	========================= */

	.badge {
		display: inline-flex;
		align-items: center;
		justify-content: center;

		min-width: 72px;

		padding: 5px 10px;

		border-radius: 6px;

		font-size: 13px;
		font-weight: 600;

		text-transform: capitalize;
	}

	.badge.available,
	.barrel-code-badge.available {
		background: #e8f8f0;
		color: #168762;
	}

	.badge.rented,
	.barrel-code-badge.rented {
		background: #fff3df;
		color: #b87519;
	}

	.badge.returning,
	.barrel-code-badge.returning {
		background: #eef2ff;
		color: #4564c8;
	}

	/* =========================
	   VISUAL GRID
	========================= */

	.barrel-grid {
		display: grid;

		grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));

		gap: 18px;

		padding: 22px;

		background: #fafbfc;
	}

	/* =========================
	   BARREL CARD
	========================= */

	.barrel-card {
		overflow: hidden;

		border: 1px solid #e2e7ef;
		border-top: 4px solid var(--status-color);
		border-radius: 13px;

		background: white;

		box-shadow: 0 4px 14px rgb(30 52 90 / 5%);
	}

	.barrel-card.available {
		--status-color: #26a879;

		--barrel-light: #d5f4e8;
		--barrel-main: #55c79d;
		--barrel-dark: #168762;
	}

	.barrel-card.rented {
		--status-color: #e29a35;

		--barrel-light: #ffebc8;
		--barrel-main: #f1b85d;
		--barrel-dark: #b87519;
	}

	.barrel-card.returning {
		--status-color: #5577df;

		--barrel-light: #dce4ff;
		--barrel-main: #7892e7;
		--barrel-dark: #3b59ba;
	}

	/* =========================
	   BARREL PICTURE
	========================= */

	.barrel-picture {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;

		height: 176px;

		background: linear-gradient(145deg, #f8fafc, #edf1f6);
	}

	.barrel-top,
	.barrel-bottom {
		z-index: 2;

		width: 91px;
		height: 17px;

		border: 4px solid var(--barrel-dark);
		border-radius: 50%;

		background: var(--barrel-light);
	}

	.barrel-top {
		margin-bottom: -8px;
	}

	.barrel-bottom {
		margin-top: -8px;
	}

	.barrel-body {
		position: relative;

		display: grid;
		place-items: center;

		width: 96px;
		height: 116px;

		border-right: 5px solid var(--barrel-dark);
		border-left: 5px solid var(--barrel-dark);

		border-radius: 15px / 42px;

		background: linear-gradient(
			90deg,
			var(--barrel-dark),
			var(--barrel-main) 18%,
			var(--barrel-light) 49%,
			var(--barrel-main) 78%,
			var(--barrel-dark)
		);

		box-shadow: 8px 10px 18px rgb(30 52 90 / 15%);
	}

	.band {
		position: absolute;

		right: -8px;
		left: -8px;

		height: 7px;

		border-radius: 5px;

		background: var(--barrel-dark);
	}

	.band.top {
		top: 25px;
	}

	.band.bottom {
		bottom: 25px;
	}

	.barrel-code {
		z-index: 1;

		padding: 5px 8px;

		border-radius: 5px;

		background: rgb(255 255 255 / 82%);
		color: #25314d;

		font-size: 13px;
		font-weight: 800;

		box-shadow: 0 2px 6px rgb(30 52 90 / 12%);
	}

	/* =========================
	   BARREL INFO
	========================= */

	.barrel-info {
		display: flex;
		align-items: flex-start;
		justify-content: space-between;

		gap: 10px;

		padding: 16px 16px 12px;
	}

	.barrel-info strong {
		display: block;

		color: #202a40;

		font-size: 14px;
	}

	.barrel-info > .badge {
		display: inline-flex;
	}

	/* =========================
	   BARREL DETAILS
	========================= */

	dl {
		display: grid;

		grid-template-columns: 1fr 1fr;

		margin: 0;

		padding: 12px 16px 16px;

		border-top: 1px solid #edf0f4;
	}

	dl div + div {
		padding-left: 14px;

		border-left: 1px solid #edf0f4;
	}

	dt {
		margin-bottom: 4px;

		color: #8a94a6;

		font-size: 10px;
		font-weight: 700;

		text-transform: uppercase;
	}

	dd {
		overflow: hidden;

		margin: 0;

		color: #485267;

		font-size: 12px;

		text-overflow: ellipsis;
		white-space: nowrap;
	}

	/* =========================
	   ACTION
	========================= */

	.action-column {
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

		right: 0;
		bottom: 42px;

		z-index: 1000;

		width: 170px;

		overflow: hidden;

		border: 1px solid #e5e7eb;
		border-radius: 8px;

		background: white;

		box-shadow: 0 10px 30px rgba(15, 23, 42, 0.14);
	}

	.dropdown-menu button {
		width: 100%;

		padding: 10px 14px;

		border: none;

		background: white;
		color: #202939;

		text-align: left;

		font-size: 14px;
		font-weight: 500;

		cursor: pointer;
	}

	.dropdown-menu button:hover {
		background: #f3f4f6;
	}

	.menu-divider {
		height: 1px;

		background: #e5e7eb;
	}

	.dropdown-menu .delete-action {
		color: #dc2626;
	}

	.dropdown-menu .delete-action:hover {
		background: #fef2f2;
	}

	.dropdown-menu .delete-action:disabled {
		background: white;
		color: #9ca3af;

		opacity: 0.6;

		cursor: not-allowed;
	}

	/* =========================
	   PANEL
	========================= */

	.barrels-panel {
		overflow: visible;
	}

	/* =========================
	   RESPONSIVE
	========================= */

	@media (max-width: 760px) {
		.search {
			width: 100%;
		}

		.view-switch {
			width: 100%;
		}

		.view-switch button {
			flex: 1;
		}

		.legend {
			justify-content: flex-start;
			flex-wrap: wrap;
		}

		.barrel-grid {
			grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));

			padding: 15px;
		}

		.data-table th,
		.data-table td {
			padding: 12px 10px;
		}
	}
</style>
