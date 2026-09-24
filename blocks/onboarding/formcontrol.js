function changeDisplay() {
    var format = $("#id_config_contentformat").val();
    switch (format) {
        case "1024":
          $("#fitem_id_config_vimeoid").show();
          $("#fitem_id_config_youtubeid").hide();
          $("#fitem_id_config_embededurl").hide();
          break;
        case "1025":
          $("#fitem_id_config_youtubeid").show();
          $("#fitem_id_config_vimeoid").hide();
          $("#fitem_id_config_embededurl").hide();
          break;
        case "1026":
          $("#fitem_id_config_embededurl").show();
          $("#fitem_id_config_youtubeid").hide();
          $("#fitem_id_config_vimeoid").hide();
          break;
        default:
          $("#fitem_id_config_embededurl").hide();
          $("#fitem_id_config_youtubeid").hide();
          $("#fitem_id_config_vimeoid").hide();
    }
}
$("#id_config_contentformat").change(function() {
    changeDisplay();
});
$("#id_config_contentformat").ready(function(){
    changeDisplay();
});
