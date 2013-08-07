Ajaxplorer = function(session_management, node) {
	this.node = jQuery(node);
	this.session_management = session_management;
	this.handler = jQuery.proxy(this.handleEvents, this);

	/* Do NOT remove ovd.session.started in destructor as it is used as a delayed initializer */
	this.session_management.addCallback("ovd.session.started", this.handler);
}

Ajaxplorer.prototype.handleEvents = function(type, source, params) {
	if(type == "ovd.session.started") {
		var session_mode = this.session_management.session.mode;
		var self = this; /* closure */

		if(session_mode == uovd.SESSION_MODE_APPLICATIONS) {
			/* register events listeners */
			this.session_management.addCallback("ovd.session.destroying", this.handler);

			var serialized;
			try {
				// XMLSerializer exists in current Mozilla browsers
				serializer = new XMLSerializer();
				serialized = serializer.serializeToString(this.session_management.session.xml);
			} catch (e) {
				// Internet Explorer has a different approach to serializing XML
				serialized = this.session_management.session.xml;
			}

			jQuery.ajax({
				url: "/ovd/ajaxplorer.php",
				type: "POST",
				dataType: "xml",
				contentType: "text/xml",
				data: serialized,
				success: function(xml) {
					var ajaxplorer = jQuery(xml).find("ajaxplorer");
					var status = ajaxplorer.attr("status");

					if(status == "ok") {
						/* Instert ajaxplorer */
						self._show_ajaxplorer_ui();
					}
				}
			});
		}
	}

	if(type == "ovd.session.destroying" ) { /* Clean context */
		this.end();
	}
}

Ajaxplorer.prototype._show_ajaxplorer_ui = function() {
	var iframe = jQuery(document.createElement("iframe"));
	iframe.width("100%").height("100%");
	iframe.css("border", "none");
	iframe.prop("src", "ajaxplorer/");
	this.node.append(iframe);
}

Ajaxplorer.prototype.end = function() {
	if(this.session_management.session.mode == uovd.SESSION_MODE_APPLICATIONS) {
		this.node.empty();
		/* Do NOT remove ovd.session.started as it is used as a delayed initializer */
		this.session_management.removeCallback("ovd.session.destroying", this.handler);
	}
}
