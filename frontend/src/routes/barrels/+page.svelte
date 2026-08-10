<script>
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { api } from '$lib/api.js';
	import Nav from '$lib/Nav.svelte';

	let barrels = $state([]);
	let loading = $state(true);
	let errorMessage = $state('');

	let status = $state('');
	let searchCode = $state('');

	let openMenuId = $state(null);

	async function loadBarrels() {
		loading = true;
		errorMessage = '';

		try {
			const params = {};

			if (status) {
				params.status = status;
			}

			const result = await api.getBarrels(params);

			barrels = result;
		} catch (error) {
			errorMessage =
				error instanceof Error
					? error.message
					: '加载失败';
		} finally {
			loading = false;
		}
	}

	async function handleStatusUpdate(barrel, newStatus) {
		console.log(
			'Update barrel:',
			barrel.code,
			'to:',
			newStatus
		);

		barrel.status = newStatus;

		openMenuId = null;
	}

	function filteredBarrels() {
		if (!searchCode.trim()) {
			return barrels;
		}

		const keyword = searchCode
			.trim()
			.toLowerCase();

		return barrels.filter((barrel) =>
			barrel.code
				?.toLowerCase()
				.includes(keyword)
		);
	}

	onMount(loadBarrels);
</script>

<Nav />

<div class="page">

	<!-- TITLE -->
	<h1>桶状态</h1>


	<!-- FILTER BAR -->
	<div class="toolbar">

		<!-- LEFT: STATUS -->
		<div class="filter-group">
			<label for="status">
				状态
			</label>

			<select
				id="status"
				bind:value={status}
				on:change={loadBarrels}
			>
				<option value="">
					全部
				</option>

				<option value="available">
					Available
				</option>

				<option value="rented">
					在租
				</option>

				<option value="returning">
					归还中
				</option>
			</select>
		</div>


		<!-- CENTER: SEARCH -->
		<div class="search-group">
			<input
				type="text"
				placeholder="搜索桶编号"
				bind:value={searchCode}
			/>
		</div>


		<!-- RIGHT: CREATE BARREL -->
		<button
			type="button"
			on:click={() => goto('/barrels/create')}
		>
			+ 添加桶
		</button>

	</div>


	<!-- TABLE -->
	<div class="table-card">

		{#if loading}

			<div class="message">
				加载中...
			</div>

		{:else if errorMessage}

			<div class="message error">
				{errorMessage}
			</div>

		{:else}

			<table>

				<thead>
					<tr>
						<th>桶Code</th>
						<th>Status</th>
						<th>Invoice Number</th>
						<th class="action-column"></th>
					</tr>
				</thead>

				<tbody>

					{#each filteredBarrels() as barrel (barrel.id)}

						<tr>

						<td class="code">
							{barrel.code}
						</td>

						<td>
							<span
								class="status-badge"
								class:available={barrel.status === 'available'}
								class:rented={barrel.status === 'rented'}
								class:returning={barrel.status === 'returning'}
							>
								{barrel.status}
							</span>
						</td>

						<td>
							{barrel.invoice_no ?? '-'}
						</td>

						<td class="action-cell">

							<div class="action-menu">

								<button
									type="button"
									class="more-button"
									on:click={() =>
										openMenuId =
											openMenuId === barrel.id
												? null
												: barrel.id
									}
								>
									⋯
								</button>

								{#if openMenuId === barrel.id}

									<div class="dropdown-menu">

										<button
											type="button"
											on:click={() =>
												handleStatusUpdate(
													barrel,
													'available'
												)
											}
										>
											Set Available
										</button>

										<button
											type="button"
											on:click={() =>
												handleStatusUpdate(
													barrel,
													'rented'
												)
											}
										>
											Set Rented
										</button>

										<button
											type="button"
											on:click={() =>
												handleStatusUpdate(
													barrel,
													'returning'
												)
											}
										>
											Set Returning
										</button>

									</div>

								{/if}

							</div>

						</td>

					</tr>

					{:else}

						<tr>
							<td
								colspan="3"
								class="empty"
							>
								没有桶记录
							</td>
						</tr>

					{/each}

				</tbody>

			</table>

		{/if}

	</div>

</div>


<style>

	/* ===============================
	   PAGE
	================================ */

	.page {
		margin: 36px 48px 60px 48px;
		font-family:
			Arial,
			Helvetica,
			sans-serif;
	}

	h1 {
		margin: 0 0 26px 0;

		font-size: 34px;
		font-weight: 700;

		color: #111827;
	}


	/* ===============================
	   TOOLBAR
	================================ */

	.toolbar {
		display: grid;

		grid-template-columns:
			1fr
			1.2fr
			auto;

		align-items: end;

		gap: 30px;

		margin-bottom: 30px;
	}


	/* STATUS */

	.filter-group {
		display: flex;
		flex-direction: column;

		gap: 7px;
	}

	.filter-group label {
		font-size: 14px;
		font-weight: 600;

		color: #374151;
	}

	.filter-group select {
		width: 100%;

		height: 44px;

		padding: 0 14px;

		border: 1px solid #d1d5db;
		border-radius: 6px;

		background: white;

		font-size: 15px;

		outline: none;
	}


	/* SEARCH */

	.search-group input {
		width: 100%;
		height: 44px;

		box-sizing: border-box;

		padding: 0 15px;

		border: 1px solid #d1d5db;
		border-radius: 6px;

		font-size: 15px;

		outline: none;
	}

	.search-group input:focus,
	.filter-group select:focus {
		border-color: #2563eb;

		box-shadow:
			0 0 0 1px #2563eb;
	}


	/* CREATE */

	.create-group {
		display: flex;

		justify-content: flex-end;
	}

	.create-group button {
		height: 44px;

		padding: 0 25px;

		border: none;
		border-radius: 6px;

		background: #2563eb;

		color: white;

		font-size: 15px;
		font-weight: 500;

		cursor: pointer;
	}

	.create-group button:hover {
		background: #1d4ed8;
	}


	/* ===============================
	   TABLE
	================================ */

	.table-card {
		border: 1px solid #e5e7eb;
		border-radius: 8px;

		overflow: visible;

		background: white;
	}

	table {
		width: 100%;

		border-collapse: collapse;
	}

	th {
		padding: 16px 18px;

		background: #f8fafc;

		border-bottom: 1px solid #e5e7eb;

		text-align: left;

		font-size: 14px;
		font-weight: 600;

		color: #374151;
	}

	td {
		padding: 17px 18px;

		border-bottom: 1px solid #e5e7eb;

		font-size: 14px;

		color: #111827;
	}

	tbody tr:last-child td {
		border-bottom: none;
	}


	/* ===============================
	   STATUS BADGE
	================================ */

	.status-badge {
		display: inline-block;

		padding: 5px 9px;

		border-radius: 5px;

		font-size: 13px;
		font-weight: 600;

		background: #f3f4f6;

		color: #4b5563;
	}

	.status-badge.available {
		background: #dcfce7;
		color: #15803d;
	}

	.status-badge.rented {
		background: #dbeafe;
		color: #1d4ed8;
	}

	.status-badge.returning {
		background: #fef3c7;
		color: #b45309;
	}

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
		width: 36px;
		height: 34px;

		border: 1px solid #d1d5db;
		border-radius: 6px;

		background: white;

		font-size: 22px;
		line-height: 20px;

		cursor: pointer;

		color: #374151;
	}

	.more-button:hover {
		background: #f3f4f6;
	}

	.dropdown-menu {
		position: absolute;

		top: 40px;
		right: 0;

		width: 160px;

		background: white;

		border: 1px solid #e5e7eb;
		border-radius: 7px;

		box-shadow:
			0 8px 20px rgba(0, 0, 0, 0.12);

		z-index: 20;

		overflow: hidden;
	}

	.dropdown-menu button {
		width: 100%;

		padding: 10px 14px;

		border: none;
		background: white;

		text-align: left;

		font-size: 14px;

		cursor: pointer;
	}

	.dropdown-menu button:hover {
		background: #f3f4f6;
	}


	/* ===============================
	   MESSAGE
	================================ */

	.message {
		padding: 25px;

		color: #64748b;
	}

	.error {
		color: #dc2626;
	}

	.empty {
		padding: 35px;

		text-align: center;

		color: #64748b;
	}


	/* ===============================
	   RESPONSIVE
	================================ */

	@media (max-width: 800px) {

		.page {
			margin: 25px 16px;
		}

		.toolbar {
			grid-template-columns: 1fr;

			gap: 15px;
		}

		.create-group {
			justify-content: flex-start;
		}

		.create-group button {
			width: 100%;
		}
	}

</style>