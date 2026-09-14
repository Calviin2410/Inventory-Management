<script>
	import { goto } from '$app/navigation';
	import { api } from '$lib/api.js';
	import Nav from '$lib/Nav.svelte';
	import { user } from '$lib/stores/auth.js';
	import { onMount } from 'svelte';
	import { guardUnsaved } from '$lib/unsaved.js';
	let code = $state('');
	let saving = $state(false);
	let errorMessage = $state('');
	let formDirty = $state(false);
	guardUnsaved(() => formDirty);

	async function handleSubmit(event) {
		event.preventDefault();

		if (saving) {
			return;
		}

		if (!code.trim()) {
			errorMessage = 'Please enter barrel code.';
			return;
		}

		saving = true;
		errorMessage = '';

		try {
			console.log(
				'Creating barrel:',
				code
			);

			const result =
				await api.createBarrel({
					code: code.trim()
				});

			console.log(
				'Created barrel:',
				result
			);

			formDirty = false;
			goto('/barrels');

		} catch (error) {
			console.error(
				'Create barrel error:',
				error
			);

			errorMessage =
				error?.message ??
				'Unable to create barrel.';
		} finally {
			saving = false;
		}
	}
</script>

<Nav />

<main class="app-page create-page">
	<header class="page-heading">
		<div>
			<p class="eyebrow">BARREL INVENTORY</p>
			<h1>Add barrel</h1>
			<p>Register a new barrel and make it available for rental.</p>
		</div>
	</header>

	<form
		class="form-card"
		onsubmit={handleSubmit}
		oninput={() => formDirty = true}
	>

		<div class="form-group">
			<label for="code">
				Barrel Code
			</label>

			<input
				id="code"
				type="text"
				placeholder="e.g. 001"
				bind:value={code}
				required
			/>
		</div>

		{#if errorMessage}
			<p class="error-message">
				{errorMessage}
			</p>
		{/if}

		<div class="actions">

			<button
				type="button"
				class="cancel-button"
				onclick={() => goto('/barrels')}
			>
				Cancel
			</button>

			<button
				type="submit"
				class="create-button"
				disabled={saving}
			>
				{saving ? 'Adding…' : 'Add barrel'}
			</button>

		</div>

	</form>
</main>

<style>
	.create-page { max-width: 980px; }
	.form-card {
		max-width: 680px;
		padding: 28px;
		border: 1px solid #e3e8ef;
		border-radius: 10px;
		background: white;
		box-shadow: 0 2px 8px rgb(15 23 42 / 3%);
	}


	.form-group {
		display: flex;
		flex-direction: column;

		gap: 7px;

		margin-bottom: 20px;
	}

	label {
		font-size: 14px;
		font-weight: 600;
	}

	input {
		padding: 11px 12px;

		border: 1px solid #d1d5db;
		border-radius: 8px;

		font-size: 14px;
	}

	.actions {
		display: flex;

		justify-content: flex-end;

		gap: 10px;

		margin-top: 25px;
	}


	.cancel-button,
	.create-button {
		padding: 10px 22px;

		border-radius: 6px;

		cursor: pointer;
	}


	.cancel-button {
		border: 1px solid #d1d5db;

		background: white;
	}


	.create-button {
		border: none;

		background: #2563eb;

		color: white;
	}
	.create-button:disabled { opacity: .6; cursor: not-allowed; }
	@media (max-width: 560px) {
		.form-card { padding: 20px; }
		.actions { display: grid; grid-template-columns: 1fr 1fr; }
		.actions button { width: 100%; }
	}
</style>
