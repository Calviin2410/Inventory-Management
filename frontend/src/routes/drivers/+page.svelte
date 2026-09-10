<script>
	import { onMount } from 'svelte';

	import { api } from '$lib/api.js';
	import { user } from '$lib/stores/auth.js';
	import Nav from '$lib/Nav.svelte';

	let drivers = $state([]);
	let loading = $state(true);
	let errorMessage = $state('');

	let name = $state('');
	let phone = $state('');
	let saving = $state(false);

	let isAdmin = $derived(
		$user?.role === 'admin'
	);

	async function loadDrivers() {
		loading = true;
		errorMessage = '';

		try {
			const result = await api.getDrivers();

			drivers = Array.isArray(result)
				? result
				: result?.data ?? [];
		} catch (error) {
			errorMessage =
				error?.message ||
				'Unable to load drivers.';
		} finally {
			loading = false;
		}
	}

	async function handleSubmit(event) {
		event.preventDefault();

		if (!isAdmin || saving) {
			return;
		}

		if (!name.trim()) {
			errorMessage =
				'Please enter the driver name.';
			return;
		}

		saving = true;
		errorMessage = '';

		try {
			const created =
				await api.createDriver({
					name: name.trim(),
					phone: phone.trim() || null
				});

			drivers = [
				...drivers,
				created
			].sort((a, b) =>
				a.name.localeCompare(b.name)
			);

			name = '';
			phone = '';

		} catch (error) {
			errorMessage =
				error?.message ||
				'Unable to add driver.';
		} finally {
			saving = false;
		}
	}

	onMount(loadDrivers);
</script>

<Nav />

<main class="app-page">

	<header class="page-heading">
		<div>
			<p class="eyebrow">
				TRANSPORT
			</p>

			<h1>
				Drivers
			</h1>

			<p>
				Manage drivers used for invoice deliveries.
			</p>
		</div>
	</header>

	{#if isAdmin}

		<section class="panel add-panel">

			<h2>
				Add Driver
			</h2>

			<form
				class="driver-form"
				onsubmit={handleSubmit}
			>

				<label>
					<span>
						Driver Name
					</span>

					<input
						type="text"
						placeholder="e.g. Bryan"
						bind:value={name}
						required
					/>
				</label>

				<label>
					<span>
						Phone
					</span>

					<input
						type="text"
						placeholder="e.g. 012-345 6789"
						bind:value={phone}
					/>
				</label>

				<button
					type="submit"
					class="btn btn-primary"
					disabled={saving}
				>
					{saving
						? 'Adding...'
						: '+ Add Driver'}
				</button>

			</form>

		</section>

	{/if}

	{#if errorMessage}

		<div class="error-message">
			{errorMessage}
		</div>

	{/if}

	<section class="panel">

		{#if loading}

			<div class="state">
				Loading drivers...
			</div>

		{:else if drivers.length === 0}

			<div class="state">
				<h3>
					No drivers yet
				</h3>

				<p>
					{#if isAdmin}
						Add your first driver above.
					{:else}
						No drivers have been added yet.
					{/if}
				</p>
			</div>

		{:else}

			<table class="data-table">

				<thead>
					<tr>
						<th>
							Driver
						</th>

						<th>
							Phone
						</th>
					</tr>
				</thead>

				<tbody>

					{#each drivers as driver (driver.id)}

						<tr>
							<td class="strong">
								{driver.name}
							</td>

							<td>
								{driver.phone || '—'}
							</td>
						</tr>

					{/each}

				</tbody>

			</table>

		{/if}

	</section>

</main>

<style>
	.add-panel {
		margin-bottom: 24px;
		padding: 24px;
	}

	.add-panel h2 {
		margin: 0 0 18px;
		font-size: 18px;
	}

	.driver-form {
		display: grid;
		grid-template-columns:
			minmax(0, 1fr)
			minmax(0, 1fr)
			auto;
		align-items: end;
		gap: 16px;
	}

	label {
		display: flex;
		flex-direction: column;
		gap: 7px;
	}

	label span {
		font-size: 14px;
		font-weight: 600;
		color: #344054;
	}

	input {
		box-sizing: border-box;
		width: 100%;
		min-height: 42px;
		padding: 10px 12px;

		border: 1px solid #d0d5dd;
		border-radius: 8px;

		background: white;
		color: #101828;

		font: inherit;
	}

	input:focus {
		outline: none;
		border-color: #2563eb;
		box-shadow:
			0 0 0 3px
			rgb(37 99 235 / 10%);
	}

	.driver-form .btn {
		min-height: 42px;
		white-space: nowrap;
	}

	.error-message {
		margin-bottom: 20px;
		padding: 12px 14px;

		border: 1px solid #fecaca;
		border-radius: 8px;

		background: #fef2f2;
		color: #b42318;
	}

	@media (max-width: 760px) {
		.driver-form {
			grid-template-columns: 1fr;
		}
	}
</style>