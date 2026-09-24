<script>
	import '../app.css';

	import { onMount } from 'svelte';
	import { browser } from '$app/environment';

	import { api, getToken, setToken } from '$lib/api.js';
	import { user } from '$lib/stores/auth.js';
	let { children } = $props();

	onMount(async () => {
		if (!browser) {
			return;
		}

		const savedToken = getToken();

		if (!savedToken) {
			user.set(null);
			return;
		}

		try {
			const currentUser =
				await api.me();

			user.set(currentUser);
		} catch (error) {
			console.error(
				'Unable to restore user:',
				error
			);

			setToken(null);
			user.set(null);
		}
	});
</script>

{@render children()}
