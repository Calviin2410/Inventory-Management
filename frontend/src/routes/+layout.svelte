<script>
	import '../app.css';

	import { onMount } from 'svelte';
	import { browser } from '$app/environment';

	import { api } from '$lib/api.js';
	import { user } from '$lib/stores/auth.js';

	onMount(async () => {
		if (!browser) {
			return;
		}

		const savedToken =
			localStorage.getItem('token');

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

			localStorage.removeItem('token');
			user.set(null);
		}
	});
</script>

<slot />