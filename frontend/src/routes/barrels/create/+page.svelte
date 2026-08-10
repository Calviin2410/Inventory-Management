<script>
	import { goto } from '$app/navigation';
	import Nav from '$lib/Nav.svelte';

	let code = $state('');
</script>

<Nav />

<div class="page">

	<h1>添加桶</h1>

	<div class="form-card">

		<div class="form-group">
			<label for="code">
				桶 Code
			</label>

			<input
				id="code"
				type="text"
				placeholder="例如 001"
				bind:value={code}
			/>
		</div>

		<div class="actions">

			<button
				type="button"
				class="cancel-button"
				on:click={() => goto('/barrels')}
			>
				取消
			</button>

			<button
				type="button"
				class="create-button"
			>
				添加桶
			</button>

		</div>

	</div>

</div>

<style>
	.page {
		margin: 36px 48px;
		max-width: 700px;

		font-family: Arial, Helvetica, sans-serif;
	}

	h1 {
		margin-bottom: 28px;

		font-size: 34px;
	}


	.form-card {
		padding: 25px;

		border: 1px solid #e5e7eb;
		border-radius: 8px;

		background: white;
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
		border-radius: 6px;

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
</style>