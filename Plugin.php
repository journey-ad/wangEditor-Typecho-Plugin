<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 富文本编辑器 <a href="https://github.com/wangfupeng1988/wangEditor/" target="_blank">wangEditor</a> for Typecho
 * 
 * @package wangEditor
 * @author journey.ad
 * @version 1.0.1
 * @link https://github.com/journey-ad/wangEditor-Typecho-Plugin
 */
class wangEditor_Plugin implements Typecho_Plugin_Interface
{

    protected static $VERSION = '1.0.1';
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     * 
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        Typecho_Plugin::factory('admin/write-post.php')->richEditor = array('wangEditor_Plugin', 'Editor');
        Typecho_Plugin::factory('admin/write-page.php')->richEditor = array('wangEditor_Plugin', 'Editor');
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     * 
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate(){}

    /**
     * 获取插件配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $beautify= new Typecho_Widget_Helper_Form_Element_Radio(
            'beautify', array('1'=>_t('开启'),'0'=>_t('关闭')), '1',
            _t('自动格式化代码'),
            _t(''));
        $form->addInput($beautify);
    }

    /**
     * 个人用户的配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {}

    /**
     * 插入编辑器
     */
    public static function Editor()
    {
        $VERSION = self::$VERSION;
        $cssUrl = Helper::options()->pluginUrl.'/wangEditor/release/wangEditor.min.css?v='.$VERSION;
        $jsUrl = Helper::options()->pluginUrl.'/wangEditor/release/wangEditor.min.js?v='.$VERSION;
        $libUrl = Helper::options()->pluginUrl.'/wangEditor/lib/';
        $beautify = Typecho_Widget::widget('Widget_Options')->plugin('wangEditor')->beautify;
?>
        <script>
            var uploadURL = '<?php Helper::security()->index('/action/upload?cid=CID'); ?>';
        </script>
<?php
        if($beautify){
            echo ("<script type='text/javascript' src='".$libUrl."htmlformat.js'></script>\n");
            $do_js_beautify = 'do_js_beautify();';
        }
        echo <<<EOF
<link rel="stylesheet" href="{$cssUrl}" />
<script type="text/javascript" src="{$jsUrl}"></script>
<script>
    $(document).ready(function() {
        var textarea = $('#text').parent("p");
        $('#text').before("<div id='text-wangEditor' style='background:#fff;position:relative;'></div>");
        postwangEditor = new wangEditor("#text-wangEditor");
        postwangEditor.customConfig.zIndex = 1;
        postwangEditor.customConfig.onchange = function (html) {
            $('#text').val(html);
        }
        $('#text').hide();
        postwangEditor.create();
        postwangEditor.txt.html($('#text').text());
        $('div.w-e-toolbar').append('<div class="w-e-editor"><span class="w-e-menu w-e-visual active" onclick="changeView(\'visual\')">可视化</span><span class="w-e-menu w-e-source" onclick="changeView(\'source\')">文本</span></div>');
        changeView = function(x) {
            if(x === 'source'){
                $('.w-e-text-container').hide();
                $('#text').show();
                $('.w-e-visual').removeClass('active');
                $('.w-e-source').addClass('active');
            }else{
                postwangEditor.txt.html($('#text').val());
                $('#text').hide();
                $('.w-e-text-container').show();
                $('.w-e-source').removeClass('active');
                $('.w-e-visual').addClass('active');
            }
            {$do_js_beautify}
        }
        $(".resize").bind("mousedown",function(event){
            $(document).bind("mousemove",function(ev){
                $('.w-e-text-container').height($('#text').height());
            });
        });
        $(document).bind("mouseup",function(){
            $(this).unbind("mousemove");
        });

        // 图片附件插入
        Typecho.insertFileToEditor = function (file, url, isImage) {
            if(isImage) postwangEditor.uploadImg.insertLinkImg(url);
        };

        // 支持黏贴图片直接上传
        $(document).on('paste', function(event) {
            event = event.originalEvent;
            var cbd = event.clipboardData;
            var ua = window.navigator.userAgent;
            if (!(event.clipboardData && event.clipboardData.items)) {
                return;
            }

            if (cbd.items && cbd.items.length === 2 && cbd.items[0].kind === "string" && cbd.items[1].kind === "file" &&
                cbd.types && cbd.types.length === 2 && cbd.types[0] === "text/plain" && cbd.types[1] === "Files" &&
                ua.match(/Macintosh/i) && Number(ua.match(/Chrome\/(\d{2})/i)[1]) < 49){
                return;
            }

            var itemLength = cbd.items.length;

            if (itemLength == 0) {
                return;
            }

            if (itemLength == 1 && cbd.items[0].kind == 'string') {
                return;
            }

            if ((itemLength == 1 && cbd.items[0].kind == 'file')
                    || itemLength > 1
                ) {
                for (var i = 0; i < cbd.items.length; i++) {
                    var item = cbd.items[i];

                    if(item.kind == "file") {
                        var blob = item.getAsFile();
                        if (blob.size === 0) {
                            return;
                        }
                        var ext = 'jpg';
                        switch(blob.type) {
                            case 'image/jpeg':
                            case 'image/pjpeg':
                                ext = 'jpg';
                                break;
                            case 'image/png':
                                ext = 'png';
                                break;
                            case 'image/gif':
                                ext = 'gif';
                                break;
                        }
                        var formData = new FormData();
                        formData.append('blob', blob, Math.floor(new Date().getTime() / 1000) + '.' + ext);
                        var uploadingText = '<span>图片上传中(' + i + ')...</span>';
                        var uploadFailText = '<span>图片上传失败(' + i + ')<span>'
                        postwangEditor.cmd.do('insertHTML', uploadingText);
                        $.ajax({
                            method: 'post',
                            url: uploadURL.replace('CID', $('input[name="cid"]').val()),
                            data: formData,
                            contentType: false,
                            processData: false,
                            success: function(data) {
                                if (data[0]) {
                                    var img = document.createElement('img');
                                    img.onload = function () {
                                        img = null;
                                        postwangEditor.cmd.do('insertHTML', '<img src="' + data[0] + '" style="max-width:100%;"/>');
                                        postwangEditor.txt.html(postwangEditor.txt.html().replace('<span>图片上传中(1)...', ''));
                                        postwangEditor.txt.html(postwangEditor.txt.html().replace('</span>', ''));
                                    };
                                    img.onerror = function () {
                                        img = null;
                                        // 无法成功下载图片
                                        postwangEditor.txt.html(postwangEditor.txt.html().replace(uploadingText, uploadFailText));
                                        return;
                                    };
                                    img.onabort = function () {
                                        img = null;
                                    };
                                    img.src = data[0];
                                } else {
                                    postwangEditor.txt.html(postwangEditor.txt.html().replace(uploadingText, uploadFailText));
                                }
                            },
                            error: function() {
                                postwangEditor.txt.html(postwangEditor.txt.html().replace(uploadingText, uploadFailText));
                            }
                        });
                    }
                }
            }
        });
        function do_js_beautify() {
            js_source = $('#text').val().replace(/^\s+/, '');
            $('#text').val(style_html(js_source, 4, ' ', 80));
        };
    });
</script>
EOF;
    }
}
