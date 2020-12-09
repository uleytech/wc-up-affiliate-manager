PostAffTracker.setAccountId('default1');
PostAffTracker.setParamNameUserId('aid');
let AffiliateID = Cookies.get('aid');
try {
	PostAffTracker.track();
} catch (err) { }
