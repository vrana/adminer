// downloaded from repository by verifyVersion()
(function () { // cookie function is not defined in older versions
	var date = new Date();
	date.setDate(date.getDate() + 7); // valid for 7 days
	document.cookie = 'adminer_version=2.3.0; expires=' + date; // last released version
})();
