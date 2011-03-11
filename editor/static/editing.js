// Editor specific functions

function bodyLoad(version) {
	if (history.state) {
		onpopstate(history);
	}
}
