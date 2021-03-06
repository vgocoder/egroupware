/**
 * EGroupware eTemplate2 - JS widget class containing styles
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2011
 * @version $Id$
 */

/*egw:uses
	et2_core_widget;
*/

/**
 * Function which appends the encapsulated style data to the head tag of the
 * page.
 *
 * TODO: The style data could be parsed for rules and appended using the JS
 * stylesheet interface, allowing the style only to modifiy nodes of the current
 * template.
 *
 * @augments et2_widget
 */
var et2_styles = (function(){ "use strict"; return et2_widget.extend(
{
	/**
	 * Constructor
	 *
	 * @memberOf et2_styles
	 */
	init: function() {
		this._super.apply(this, arguments);

		// Allow no child widgets
		this.supportedWidgetClasses = [];

		// Create the style node and append it to the head node
		this.styleNode = document.createElement("style");
		this.styleNode.setAttribute("type", "text/css");

		this.head = this.egw().window.document.getElementsByTagName("head")[0];
		this.head.appendChild(this.styleNode);
	},

	destroy: function() {
		// Remove the style node again and delete any reference to it
		this.head.removeChild(this.styleNode);

		this._super.apply(this, arguments);
	},

	loadContent: function(_content) {
		if (this.styleNode.styleSheet)
		{
			// IE
			this.styleNode.styleSheet.cssText += _content;
		}
		else
		{
			this.styleNode.appendChild(document.createTextNode(_content));
		}
	},

	/**
	 * Sets the id of the DOM-Node.
	 *
	 * DOM id's have dots "." replaced with dashes "-"
	 *
	 * @param {string} _value id to set
	 */
	set_id: function(_value) {

		this.id = _value;
		this.dom_id = _value ? this.getInstanceManager().uniqueId+'_'+_value.replace(/\./g, '-') : _value;

		if (this.styleNode)
		{
			if (_value != "")
			{
				this.styleNode.setAttribute("id", this.dom_id);
			}
			else
			{
				this.styleNode.removeAttribute("id");
			}
		}
	}
});}).call(this);
et2_register_widget(et2_styles, ["styles"]);

