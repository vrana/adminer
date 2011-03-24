// Editor specific functions

function bodyLoad(version) {
	if (history.state !== undefined) {
		onpopstate(history);
	}
}
