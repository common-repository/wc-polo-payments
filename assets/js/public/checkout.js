jQuery(function ($) {
  "use strict";
  $(document.body).on("click", ".copy-qr-code", function () {
    /* Get the text field */
    var tempInput = document.createElement("input");
    var copyText = document.getElementById("pixQrCodeInput");
    tempInput.value = copyText.value;
    document.body.appendChild(tempInput);
    tempInput.select();
    tempInput.setSelectionRange(0, 99999); /* For mobile devices */
    document.execCommand("copy");
    document.body.removeChild(tempInput);

    $(".wc-polopag-qrcode-copyed").show();
  });

  function checkPixPayment() {
    var interval = 5000;
    if (typeof polopagpayments_geteway !== "undefined")
      interval = polopagpayments_geteway.checkInterval;

    var checkInt = setInterval(function () {
      $.get(woocommerce_params.ajax_url, {
        action: "polopagpayments_check",
        key: $("input[name=polopagpayments_order_key]").val(),
        nonce: polopagpayments_geteway.nonce,
      }).done(function (data) {
        if (data.paid == true) {
          clearInterval(checkInt);
          $("#watingPixPaymentBox").fadeOut(function () {
            $("#successPixPaymentBox").fadeIn();
          });
          return;
        }
      });
    }, interval);
  }

  if (!$("#successPixPaymentBox").is(":visible")) checkPixPayment();
});
