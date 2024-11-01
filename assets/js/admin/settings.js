jQuery(function ($) {
  const prefix = "woocommerce_polopagpayments_geteway_";
  $("#rangeicon").on("input", function (value) {
    const rangeValue = jQuery(this).val();
    $("#imagePixSvg").width(rangeValue);
    $("#imagePixSvg").height(rangeValue);
    $("#rangeiconsize").html(rangeValue + "px");
  });
  $("#colorpicker").colpick({
    onChange: function (c1, c2) {
      $(".pppix-c1").css("fill", "#" + c2);
      $(
        "input[name=<?php echo esc_html( $this->get_field_name( 'pix_icon_color' ) ); ?>]"
      ).val("#" + c2);
    },
  });
  $("#colordefault").on("click", function (e) {
    e.preventDefault();
    $(".pppix-c1").css("fill", "#32bcad");
    $(
      "input[name=<?php echo esc_html( $this->get_field_name( 'pix_icon_color' ) ); ?>]"
    ).val("#32bcad");
  });
  $(".woocommerce-save-button").attr("disabled", false);
});
