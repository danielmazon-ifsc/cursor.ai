/**
 * Video form contoller
 *
 * @module    video
 * @package   mod_video
 * @copyright 2020 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
//define(['jquery'], function ($) {
  //  return {
    //    init: function () {
            function changeDisplay() {
                var format = $("#id_contentformat").val();
                switch (format) {
                    case "1024":
                      $("#fitem_id_vimeoid").show();
                      $("#fitem_id_youtubeid").hide();
                      $("#fitem_id_storedvideo").hide();
                      $("#fitem_id_embededurl").hide();
                      $("#fitem_id_cardthumbnail").hide();
                      break;
                    case "1025":
                      $("#fitem_id_youtubeid").show();
                      $("#fitem_id_storedvideo").hide();
                      $("#fitem_id_vimeoid").hide();
                      $("#fitem_id_embededurl").hide();
                      $("#fitem_id_cardthumbnail").hide();
                      break;
                    case "1026":
                      $("#fitem_id_embededurl").show();
                      $("#fitem_id_cardthumbnail").show();
                      $("#fitem_id_youtubeid").hide();
                      $("#fitem_id_storedvideo").hide();
                      $("#fitem_id_vimeoid").hide();
                      break;
                    default:
                      $("#fitem_id_storedvideo").show();
                      $("#fitem_id_youtubeid").hide();
                      $("#fitem_id_vimeoid").hide();
                      $("#fitem_id_embededurl").hide();
                      $("#fitem_id_cardthumbnail").hide();
                }
            }
            $("#id_contentformat").change(function() {
                changeDisplay();
            });
            $("#id_contentformat").ready(function(){
                changeDisplay();
            });
      //  }
  //  };
//});
