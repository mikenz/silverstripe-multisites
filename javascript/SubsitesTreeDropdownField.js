(function($) {
	$.entwine('ss', function($) {
		$('.TreeDropdownField').entwine({
			subsiteID: function() {
				var subsiteSel = $('#CopyContentFromID_SubsiteID select')[0];
				if (!subsiteSel) {
					subsiteSel = $('select[name=CopyContentFromIDSubsiteID]')[0];
				}

				if(!subsiteSel) return;

				subsiteSel.onchange = (function() {
					this.loadTree(null, this._riseUp);
				}).bind(this);

				return subsiteSel.options[subsiteSel.selectedIndex].value;
			},

			getRequestParams: function() {
				var name = this.find(':input:hidden').attr('name'), obj = {};
				obj[name + '_SubsiteID'] = parseInt(this.subsiteID());
				return obj;
			}
		});
	});
})(jQuery);
