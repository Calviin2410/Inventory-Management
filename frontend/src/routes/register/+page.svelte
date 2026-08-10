<script>
	import { goto } from '$app/navigation';
	import { api, setToken } from '$lib/api.js';
	import { user } from '$lib/stores/auth.js';

	let name = '';
	let email = '';
	let password = '';
	let errorMessage = '';
	let loading = false;

	async function handleRegister() {
		errorMessage = '';
		loading = true;
		try {
			const data = await api.register(name, email, password);
			setToken(data.token);
			user.set(data.user);
			goto('/dashboard');
		} catch (err) {
			errorMessage = err.message || '注册失败';
		} finally {
			loading = false;
		}
	}
</script>

<div class="register-page">
	<form on:submit|preventDefault={handleRegister}>
		<h1>注册账号</h1>

		{#if errorMessage}
			<p class="error">{errorMessage}</p>
		{/if}

		<label>
			姓名
			<input type="text" bind:value={name} required />
		</label>

		<label>
			邮箱
			<input type="email" bind:value={email} required />
		</label>

		<label>
			密码(至少 8 位)
			<input type="password" bind:value={password} minlength="8" required />
		</label>

		<button type="submit" disabled={loading}>
			{loading ? '注册中...' : '注册'}
		</button>

		<a href="/login">已有账号?去登录</a>
	</form>
</div>

<style>
	.register-page {
		display: flex;
		justify-content: center;
		align-items: center;
		height: 100vh;
		background: #f5f5f5;
	}
	form {
		background: white;
		padding: 2rem;
		border-radius: 8px;
		box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
		width: 320px;
		display: flex;
		flex-direction: column;
		gap: 1rem;
	}
	label {
		display: flex;
		flex-direction: column;
		gap: 0.25rem;
		font-size: 0.9rem;
	}
	input {
		padding: 0.5rem;
		border: 1px solid #ccc;
		border-radius: 4px;
	}
	button {
		padding: 0.6rem;
		background: #2563eb;
		color: white;
		border: none;
		border-radius: 4px;
		cursor: pointer;
	}
	button:disabled {
		opacity: 0.6;
		cursor: not-allowed;
	}
	a {
		text-align: center;
		font-size: 0.85rem;
		color: #2563eb;
	}
	.error {
		color: #dc2626;
		font-size: 0.85rem;
	}
</style>
