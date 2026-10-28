import { tick } from "svelte";

/** Load the requested page, then return the document to its top. */
export async function loadPageAndScrollToTop(loadPage) {
	await loadPage();
	await tick();

	if (typeof window !== "undefined") {
		window.scrollTo(0, 0);
	}
}
