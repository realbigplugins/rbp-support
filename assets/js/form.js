"use strict";!function(t){t(document).ready(function(){t(".rbp-support-form").length<=0||t(".rbp-support-form").on("submit",function(e){e.preventDefault(),e.stopPropagation();var a=t(this),n=a.data("prefix"),r=window[n+"_support_form"],o=t(document.activeElement);o.attr("disabled",!0);var i={};a.find(".form-field").each(function(e,a){if(t(a).parent().hasClass("hidden"))return!0;var n=t(a).attr("name"),r=t(a).val();t(a).is('input[type="checkbox"]')&&(r=t(a).prop("checked")?1:0),i[n]=r}),i.action="rbp_support_form";var p=a.find('input[id$="_support_nonce"]');i[p.attr("name")]=p.val(),i.plugin_prefix=n,i.license_data=r.license_data,t.ajax({type:"POST",url:r.ajaxUrl,data:i,success:function(t){a.parent().find(".success-message").fadeIn(),a.fadeOut()},error:function(t,e,a){console.log(a),o.attr("disabled",!1)}})})})}(jQuery);
//# sourceMappingURL=form.js.map