/**
 * 2007-2016 PrestaShop
 *
 * thirty bees is an extension to the PrestaShop e-commerce software developed by PrestaShop SA
 * Copyright (C) 2017-2024 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://www.thirtybees.com for more information.
 *
 *  @author    thirty bees <contact@thirtybees.com>
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2017-2024 thirty bees
 *  @copyright 2007-2016 PrestaShop SA
 *  @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *  PrestaShop is an internationally registered trademark & property of PrestaShop SA
 */

/* global window, tinyMCE, tinymce_override_config, ad, iso */

function tinySetup(config) {
  if (typeof tinyMCE === 'undefined') {
    setTimeout(function () {
      tinySetup(config);
    }, 100);
    return;
  }

  if (!config) {
    config = {};
  }

  if (typeof config['editor_selector'] !== 'undefined') {
    config.selector = '.' + config['editor_selector'];
  }

  function setupStickyToolbar(editor) {
    function applyStickyMenubar() {
      const container = editor.getContainer();
      if (!container) {
        return;
      }

      const menubar = container.querySelector('.mce-menubar');
      const toolbarGrp = container.querySelector('.mce-toolbar-grp');
      if (!menubar && !toolbarGrp) {
        return;
      }

      const overflow = 'visible';
      container.style.overflow = overflow;
      if (container.parentElement) {
        container.parentElement.style.overflow = overflow;
      }

      if (menubar) {
        Object.assign(menubar.style, {
          position: 'sticky',
          top: '136px',
          zIndex: '100',
          backgroundColor: 'white',
          display: 'block',
          width: '100%'
        });
      }

      if (toolbarGrp) {
        Object.assign(toolbarGrp.style, {
          position: 'sticky',
          top: '164px',
          zIndex: '99',
          backgroundColor: 'white',
          display: 'block',
          width: '100%',
          paddingBottom: '5px'
        });
      }
    }

    editor.on('init', function () {
      applyStickyMenubar();
    });
    editor.on('ResizeEditor', function () {
      applyStickyMenubar();
    });
  }

  function setupBlogPostView(editor) {
    function toggleBlogPostMode(enable) {
      const body = editor.getBody();
      const doc = editor.getDoc();
      if (!body || !doc) {
        return;
      }

      if (enable) {
        editor.dom.addClass(body, 'article');
        editor.dom.addClass(body, 'max-w-prose');
        editor.dom.setStyles(body, { 'padding': '25px' });
        editor.dom.loadCSS('/themes/genzo_theme/css/autoload/tailwind.css');
      } else {
        editor.dom.removeClass(body, 'article');
        editor.dom.removeClass(body, 'max-w-prose');
        editor.dom.setStyles(body, { 'padding': '' });
      }

      const links = doc.getElementsByTagName('link');
      for (let i = 0; i < links.length; i++) {
        if (links[i].href.indexOf('content.min.css') !== -1) {
          links[i].disabled = enable;
        }
      }

      editor.execCommand('mceAutoResize');
      editor.fire('ResizeEditor');
    }

    editor.addMenuItem('blog_post', {
      text: 'Blogbeitrag',
      context: 'view',
      selectable: true,
      onclick: function () {
        const isBlogPost = editor.dom.hasClass(editor.getBody(), 'article');
        toggleBlogPostMode(!isBlogPost);
        this.active(!isBlogPost);
      },
      onPostRender: function () {
        const isBlogPost = editor.dom.hasClass(editor.getBody(), 'article');
        if (isBlogPost) {
          toggleBlogPostMode(true);
        }
        this.active(isBlogPost);
      }
    });
  }

  let defaultConfig = {
    selector: ".rte",
    plugins: "colorpicker link image paste pagebreak table contextmenu filemanager table code media autoresize textcolor anchor directionality codemirror",
    browser_spellcheck: true,
    toolbar1: "code,|,bold,italic,underline,strikethrough,|,alignleft,aligncenter,alignright,alignfull,formatselect,styleselect,|,blockquote,colorpicker,pasteword,|,bullist,numlist,|,outdent,indent,|,link,unlink,|,anchor,|,media,image",
    toolbar2: "",
    external_filemanager_path: ad + "/filemanager/",
    filemanager_title: "File manager",
    external_plugins: { "filemanager": ad + "/filemanager/plugin.min.js" },
    language: iso,
    skin: "prestashop",
    statusbar: false,
    relative_urls: false,
    convert_urls: false,
    entity_encoding: "raw",
    extended_valid_elements: "em[class|name|id]",
    valid_children: "+*[*]",
    valid_elements: "*[*]",
    style_formats: [
      {
        title: 'Listen Icons',
        items: [
          { title: 'Vorteil (Check Circle)', selector: 'li', classes: 'list-icon-positive' },
          { title: 'Nachteil (Facedown Smile)', selector: 'li', classes: 'list-icon-negative' }
        ]
      }
    ],
    video_template_callback: (data) =>
      `<div class="embed-responsive embed-responsive-16by9"><video class="embed-responsive-item" width="${data.width}" height="${data.height}"${data.poster ? ` poster="${data.poster}"` : ''} preload="none" controls="controls">\n`
      + `<source src="${data.source1}"${data.source1mime ? ` type="${data.source1mime}"` : ''}>\n`
      + (data.source2 ? `<source src="${data.source2}"${data.source2mime ? ` type="${data.source2mime}"` : ''}>\n` : '')
      + '</video></div>',
    // Prevents empty <p></p> generation fix
    forced_root_block: false,  // Prevents automatically wrapping content in <p> tags
    force_br_newlines: false,  // Prevents <br> from being inserted when pressing Enter
    force_p_newlines: true,  // Ensures that new lines are wrapped in <p> tags
    convert_newlines_to_brs: false,  // Prevents new lines from being converted into <br>
    toolbar_sticky: true,
    
    menu: {
      edit: { title: 'Edit', items: 'undo redo | cut copy paste | selectall' },
      insert: { title: 'Insert', items: 'media image link | pagebreak' },
      view: { title: 'View', items: 'visualaid | blog_post' },
      format: {
        title: 'Format',
        items: 'bold italic underline strikethrough superscript subscript | formats | removeformat'
      },
      table: { title: 'Table', items: 'inserttable tableprops deletetable | cell row column' },
      tools: { title: 'Tools', items: 'code' }
    },
    autoresize_min_height: 100,
    codemirror: {
      indentOnInit: true,
      path: 'codemirror-5.65',
      config: {
        lineNumbers: true,
      },
      width: 1200,
      height: 600,
      saveCursorPosition: false,
    },
    init_instance_callback: function (editor) {
      editor.on('PostProcess', function (e) {
        e.content = e.content.replace(/\s*\/>/g, '>');
      });
    },
  };

  // allow extending default config
  if (typeof window['tinymce_override_config'] !== 'undefined') {
    defaultConfig = {
      ...defaultConfig,
      ...window['tinymce_override_config']
    };
  }

  config = {
    ...defaultConfig,
    ...config
  };

  const configuredSetup = config.setup;
  config.setup = function (editor) {
    if (config.toolbar_sticky) {
      setupStickyToolbar(editor);
    }

    setupBlogPostView(editor);

    if (typeof configuredSetup === 'function') {
      configuredSetup(editor);
    }
  };

  tinyMCE.init(config);
}
