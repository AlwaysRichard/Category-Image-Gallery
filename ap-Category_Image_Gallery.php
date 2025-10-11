<?php
/**
 * Plugin Name: AP Category Image Gallery
 * Description: Category-based image galleries. Layouts: tiled (Jetpack-like justified), grid, masonry, collage (metro). Selects images only from posts in a given category. Supports per_row/cols, gutter, max, cycle, include_draft, click_menu, mode (post|attachment), crop toggle, and advanced tiled tuning.
 * Version:     2.1.0
 * Author:      AlwaysPhotographing
 * Text Domain: ap-category-image-gallery
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AP_Category_Image_Gallery {
    const SHORTCODE = 'category_image_gallery';
    const HANDLE    = 'ap-category-image-gallery';

    public function __construct() {
        add_shortcode( self::SHORTCODE, [ $this, 'shortcode' ] );
        // Multi-line alias: attributes can be written on separate lines between open/close tags
        add_shortcode( 'category_image_gallery_ml', [ $this, 'shortcode_multiline' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function enqueue_assets() {
        // --- CSS ---
        $css = <<<CSS
/* Base */
.ap-gallery{line-height:0}
.ap-gallery__item{position:relative; display:block; overflow:hidden; margin:0} /* ← reset */
.ap-gallery figure{margin:0}               /* ← neutralize theme's figure margin */
.ap-gallery__img{display:block; width:100%; height:auto; border-radius:0}
.ap-focus-outline:focus{outline:2px solid #2271b1; outline-offset:2px}

/* GRID (uniform tiles) */
.ap-gallery--grid{
  display:grid;
  grid-template-columns: var(--ap-grid-cols, repeat(3,1fr));
  gap: var(--ap-gap,8px);
}

/* MASONRY (waterfall) */
.ap-gallery--masonry{column-count: var(--ap-columns,3); column-gap: var(--ap-gap,12px);}
.ap-gallery--masonry .ap-gallery__item{break-inside: avoid; margin-bottom: var(--ap-gap,12px);}

/* TILED (Justified rows; JS sets per-row widths/heights) */
.ap-gallery--tiled{
  display:flex;
  flex-wrap:wrap;
  column-gap: var(--ap-gap,8px);
  row-gap: var(--ap-vgap, var(--ap-gap,8px)); /* ← vertical = horizontal by default */
}
.ap-gallery--tiled .ap-gallery__item{flex:0 0 auto}

/* COLLAGE (Metro grid) */
.ap-gallery--collage{
  display:grid;
  grid-auto-flow: dense;
  grid-auto-rows: var(--ap-row, 12px);
  grid-template-columns: repeat(var(--ap-cols, 6), 1fr);
  gap: var(--ap-gap, 8px);
}
.ap-gallery--collage .ap-gallery__item{position:relative}
.ap-gallery--collage .ap-gallery__img{
  position:absolute; inset:0; width:100%; height:100%; object-fit:cover;
}
/* No-crop variant (letterbox/pillarbox as needed) */
.ap--nocrop.ap-gallery--collage{ grid-auto-rows: auto; }
.ap--nocrop.ap-gallery--collage .ap-gallery__img{
  position:static; width:100%; height:auto; object-fit:contain;
  background: var(--ap-bg, transparent);
}

/* Optional click menu */
.ap-gallery__menu-toggle{position:absolute; inset:0; display:block}
.ap-gallery__menu{
  position:absolute; bottom:10px; left:10px; min-width:160px;
  background:#fff; border:1px solid rgba(0,0,0,.12);
  box-shadow:0 8px 24px rgba(0,0,0,.18);
  border-radius:10px; padding:8px 12px; display:none; z-index:4;
  font-size:14px; line-height:1.5;
}
.ap-gallery__menu-label{display:inline; color:#666; font-weight:500; margin-right:8px}
.ap-gallery__menu a{display:inline; padding:0; color:#2271b1; text-decoration:none; font-weight:500}
.ap-gallery__menu a:hover{text-decoration:underline}
.ap-gallery__menu button{display:inline; padding:0; color:#2271b1; background:none; border:none; font:inherit; cursor:pointer; text-decoration:none; font-weight:500}
.ap-gallery__menu button:hover{text-decoration:underline}
.ap-gallery__menu-sep{display:inline; color:#999; margin:0 6px}
.ap-gallery__item:hover .ap-gallery__menu-toggle{cursor:pointer}
.ap-gallery__item:focus-within .ap-gallery__menu{display:block}
.ap-gallery__menu.is-open{display:block}

/* Lightbox for full-image viewing */
.ap-lightbox{
  position:fixed; inset:0; background:rgba(0,0,0,0.92); z-index:9999;
  display:none; align-items:center; justify-content:center; flex-direction:column;
}
.ap-lightbox.is-open{display:flex}
.ap-lightbox__img{max-width:90vw; max-height:80vh; object-fit:contain}
.ap-lightbox__caption{
  color:#fff; text-align:center; margin-top:15px; font-size:16px;
  max-width:90vw; padding:0 20px; line-height:1.5;
  font-family: 'Courier New', Courier, monospace;
}
.ap-lightbox__close{
  position:absolute; top:60px; right:20px;
  width:40px; height:40px; border:none; background:#fff;
  border-radius:50%; cursor:pointer; font-size:24px; line-height:1;
  display:flex; align-items:center; justify-content:center;
  box-shadow:0 2px 8px rgba(0,0,0,0.3);
}
.ap-lightbox__close:hover{background:#f0f0f0}
.ap-lightbox__nav{
  position:absolute; bottom:20px; right:20px;
  display:flex; gap:10px;
}
.ap-lightbox__nav button{
  width:50px; height:50px; border:none; background:#fff;
  border-radius:8px; cursor:pointer; font-size:18px; font-weight:bold;
  display:flex; align-items:center; justify-content:center;
  box-shadow:0 2px 8px rgba(0,0,0,0.3);
}
.ap-lightbox__nav button:hover{background:#f0f0f0}
.ap-lightbox__nav button:disabled{opacity:0.4; cursor:not-allowed}
CSS;

        wp_register_style( self::HANDLE, false, [], '2.1.0' );
        wp_add_inline_style( self::HANDLE, $css );
        wp_enqueue_style( self::HANDLE );

        // --- JS (click menu + tiled + collage sizing) ---
        $js = <<<JS
(function(){
  // ----- Lightbox for image viewing -----
  var lightboxHTML = '<div class="ap-lightbox" id="ap-lightbox">' +
    '<button class="ap-lightbox__close" aria-label="Close">&times;</button>' +
    '<img class="ap-lightbox__img" src="" alt="" />' +
    '<div class="ap-lightbox__caption"></div>' +
    '<div class="ap-lightbox__nav">' +
      '<button class="ap-lightbox__prev" aria-label="Previous">&larr;</button>' +
      '<button class="ap-lightbox__next" aria-label="Next">&rarr;</button>' +
    '</div>' +
  '</div>';
  
  // Insert lightbox once into body
  if (!document.getElementById('ap-lightbox')) {
    document.body.insertAdjacentHTML('beforeend', lightboxHTML);
  }
  
  var lightbox = document.getElementById('ap-lightbox');
  var lightboxImg = lightbox.querySelector('.ap-lightbox__img');
  var lightboxCaption = lightbox.querySelector('.ap-lightbox__caption');
  var closeBtn = lightbox.querySelector('.ap-lightbox__close');
  var prevBtn = lightbox.querySelector('.ap-lightbox__prev');
  var nextBtn = lightbox.querySelector('.ap-lightbox__next');
  var currentGallery = null;
  var currentIndex = 0;
  
  function openLightbox(gallery, index) {
    currentGallery = gallery;
    currentIndex = index;
    updateLightboxImage();
    lightbox.classList.add('is-open');
    document.body.style.overflow = 'hidden';
  }
  
  function closeLightbox() {
    lightbox.classList.remove('is-open');
    document.body.style.overflow = '';
    currentGallery = null;
  }
  
  function updateLightboxImage() {
    if (!currentGallery) return;
    var items = Array.prototype.slice.call(currentGallery.querySelectorAll('.ap-gallery__item'));
    if (currentIndex < 0) currentIndex = items.length - 1;
    if (currentIndex >= items.length) currentIndex = 0;
    
    var currentItem = items[currentIndex];
    var img = currentItem.querySelector('img');
    if (img) {
      var link = currentItem.querySelector('a.ap-gallery__link');
      // Always use the data-full-image attribute for the lightbox source
      var src = link ? link.getAttribute('data-full-image') : img.src;
      lightboxImg.src = src || img.src;
      lightboxImg.alt = img.alt;
      
      // Get caption from data attribute
      var caption = currentItem.getAttribute('data-caption') || '';
      lightboxCaption.textContent = caption;
    }
    
    prevBtn.disabled = (items.length <= 1);
    nextBtn.disabled = (items.length <= 1);
  }
  
  function navigate(direction) {
    currentIndex += direction;
    updateLightboxImage();
  }
  
  // Event listeners
  closeBtn.addEventListener('click', closeLightbox);
  prevBtn.addEventListener('click', function() { navigate(-1); });
  nextBtn.addEventListener('click', function() { navigate(1); });
  
  // Close on background click
  lightbox.addEventListener('click', function(e) {
    if (e.target === lightbox) closeLightbox();
  });
  
  // Close on Escape key
  document.addEventListener('keydown', function(e) {
    if (!lightbox.classList.contains('is-open')) return;
    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowLeft') navigate(-1);
    if (e.key === 'ArrowRight') navigate(1);
  });
  
  // Attach lightbox to image links (use capture phase to ensure we catch it first)
  document.addEventListener('click', function(e) {
    var link = e.target.closest('a.ap-gallery__link');
    if (!link) return;
    
    var gallery = link.closest('.ap-gallery');
    if (!gallery) return;
    
    // Check if this gallery has the lightbox marker attribute
    if (!gallery.hasAttribute('data-lightbox')) return;
    
    // Always prevent default for gallery links - they should open in lightbox
    e.preventDefault();
    e.stopPropagation();
    var items = Array.prototype.slice.call(gallery.querySelectorAll('.ap-gallery__item'));
    var currentItem = link.closest('.ap-gallery__item');
    var index = items.indexOf(currentItem);
    openLightbox(gallery, index);
  }, true);

  // ----- Optional click menu -----
  document.addEventListener('click', function(e){
    var t = e.target;
    
    // Handle lightbox trigger button in menu
    if (t.classList.contains('ap-gallery__lightbox-trigger')) {
      var gallery = t.closest('.ap-gallery');
      var currentItem = t.closest('.ap-gallery__item');
      if (gallery && currentItem && gallery.hasAttribute('data-lightbox')) {
        var items = Array.prototype.slice.call(gallery.querySelectorAll('.ap-gallery__item'));
        var index = items.indexOf(currentItem);
        openLightbox(gallery, index);
        // Close the menu
        document.querySelectorAll('.ap-gallery__menu.is-open').forEach(function(m){ m.classList.remove('is-open'); });
        e.preventDefault();
        return;
      }
    }
    
    if (!t.closest('.ap-gallery__menu') && !t.closest('.ap-gallery__menu-toggle')) {
      document.querySelectorAll('.ap-gallery__menu.is-open').forEach(function(m){ m.classList.remove('is-open'); });
      return;
    }
    if (t.classList.contains('ap-gallery__menu-toggle')) {
      var menu = t.closest('.ap-gallery__item').querySelector('.ap-gallery__menu');
      if (menu) {
        var open = menu.classList.contains('is-open');
        document.querySelectorAll('.ap-gallery__menu.is-open').forEach(function(m){ m.classList.remove('is-open'); });
        if(!open) menu.classList.add('is-open');
        e.preventDefault();
      }
    }
  }, true);

  // Helpers
  function getAspect(el){
    var ar = parseFloat(el.getAttribute('data-aspect'));
    if (ar && ar > 0) return ar;
    var img = el.querySelector('img');
    if (img && img.naturalWidth && img.naturalHeight) return img.naturalWidth / img.naturalHeight;
    return 1.5;
  }

  // ----- TILED (Jetpack-like justified) -----
  function sizeRow(g, row, containerWidth, gap, targetH, justify){
    var totalGap = gap * (row.length - 1);
    var widths   = row.map(function(x){ return x.ar * targetH; });
    var sumW     = widths.reduce(function(s,w){ return s + w; }, 0);
    var factor   = justify ? (containerWidth - totalGap) / sumW : 1;
    if (!isFinite(factor) || factor <= 0) factor = 1;
    var used = 0;
    for (var i=0; i<row.length; i++){
      var w = (i === row.length - 1) ? Math.max(0, containerWidth - totalGap - used)
                                     : Math.round(widths[i] * factor);
      used += w;
      var item = row[i].el, img = item.querySelector('img');
      var hpx  = Math.round(targetH * factor);
      item.style.width  = w + 'px';
      item.style.height = hpx + 'px';
      if (img){ img.style.width='auto'; img.style.height=hpx+'px'; }
    }
  }

  function justifyLayout(g){
    if (!g || !g.classList.contains('ap-gallery--tiled')) return;

    var cs   = getComputedStyle(g);
    var gap  = parseFloat(cs.getPropertyValue('--ap-gap')) || 8;

    var baseH      = parseFloat(g.getAttribute('data-row-height')) || 220;
    var tol        = parseFloat(g.getAttribute('data-tolerance')) || 0.25; // ±25%
    var panoThresh = parseFloat(g.getAttribute('data-pano-threshold')) || 2.6;
    var minPer     = parseInt(g.getAttribute('data-min-per-row')) || 2;
    var maxPer     = parseInt(g.getAttribute('data-max-per-row')) || 5;
    var lastRow    = g.getAttribute('data-last-row') === 'justify' ? 'justify' : 'left';

    var items = Array.prototype.slice.call(g.querySelectorAll('.ap-gallery__item'));
    if (!items.length) return;

    // Reset inline sizes
    items.forEach(function(it){
      it.style.width=''; it.style.height='';
      var img=it.querySelector('img'); if(img){ img.style.width=''; img.style.height=''; }
    });

    var cw = Math.floor(g.clientWidth);
    if (!cw) return;

    var rows = [];
    var row  = [];
    var rowAR = 0;

    // Greedy packing with panorama handling and tolerance
    for (var i=0; i<items.length; i++){
      var el = items[i];
      var ar = getAspect(el);

      // Panorama: solo row
      if (ar >= panoThresh) {
        if (row.length) { rows.push(row); row=[]; rowAR=0; }
        rows.push([{el:el, ar:ar, pano:true}]);
        continue;
      }

      row.push({el:el, ar:ar});
      rowAR += ar;

      var totalGap = gap * (row.length - 1);
      var widthAtBase = baseH * rowAR + totalGap;

      if (widthAtBase >= cw || row.length >= maxPer) {
        var factor = (cw - totalGap) / (baseH * rowAR);
        if (factor > (1 + tol) && row.length < maxPer && i < items.length - 1) {
          continue; // allow one more image to avoid overstretching
        }
        rows.push(row);
        row = []; rowAR = 0;
      }
    }
    if (row.length) rows.push(row);

    // Widow/orphan fix
    if (rows.length > 1 && rows[rows.length-1].length < minPer) {
      var last = rows[rows.length-1];
      var prev = rows[rows.length-2];
      if (prev && prev.length > minPer) {
        last.unshift(prev.pop());
      }
    }

    // Render rows
    for (var r=0; r<rows.length; r++){
      var cur = rows[r];
      var isLast = (r === rows.length - 1);

      if (cur.length === 1 && cur[0].pano) {
        var panoH = baseH;
        panoH = Math.max(baseH*(1 - tol), Math.min(baseH*(1 + tol), panoH));
        sizeRow(g, cur, cw, gap, panoH, true);
        continue;
      }

      var sumAR = cur.reduce(function(s,x){ return s + x.ar; }, 0);
      var totalGap = gap * (cur.length - 1);
      var targetH  = (cw - totalGap) / sumAR;  // exact height to fill width
      var minH     = baseH * (1 - tol);
      var maxH     = baseH * (1 + tol);

      if (isLast && lastRow === 'left') {
        targetH = Math.max(minH, Math.min(maxH, baseH));
        sizeRow(g, cur, cw, gap, targetH, false);
      } else {
        targetH = Math.max(minH, Math.min(maxH, targetH));
        sizeRow(g, cur, cw, gap, targetH, true);
      }
    }
  }

  // ----- COLLAGE (Metro grid) -----
  function collageLayout(g){
    if (!g || !g.classList.contains('ap-gallery--collage')) return;
    var items = Array.prototype.slice.call(g.querySelectorAll('.ap-gallery__item')); if (!items.length) return;

    var nocrop = g.classList.contains('ap--nocrop');
    var cols  = parseInt(getComputedStyle(g).getPropertyValue('--ap-cols')) || 6;

    var minCol = parseInt(g.getAttribute('data-min-col')) || 2;
    var maxCol = parseInt(g.getAttribute('data-max-col')) || 3;
    var featEvery = parseInt(g.getAttribute('data-feature-every')) || 0;
    var featW = parseInt(g.getAttribute('data-feature-w')) || 3;
    var featH = parseInt(g.getAttribute('data-feature-h')) || 4;

    items.forEach(function(it,idx){
      var ar = getAspect(it);
      var isFeat = (featEvery>0 && ((idx+1) % featEvery === 0));
      var colSpan;

      if (isFeat){
        colSpan = Math.min(cols, Math.max(featW, maxCol));
      } else {
        if (ar >= 1.7)      colSpan = Math.min(maxCol, 3);
        else if (ar >= 1.25) colSpan = Math.min(maxCol, 2);
        else                 colSpan = Math.max(minCol, 2);
      }

      it.style.gridColumn = 'span ' + Math.max(1, Math.min(cols, colSpan));

      if (nocrop){
        it.style.gridRow = 'auto';
        it.style.height  = 'auto';
      } else {
        var gap = parseFloat(getComputedStyle(g).getPropertyValue('--ap-gap')) || 8;
        var row = parseFloat(getComputedStyle(g).getPropertyValue('--ap-row')) || 12;
        var approxWidthPx = (colSpan / cols) * g.clientWidth;
        var heightPx = approxWidthPx / (ar || 1.5);
        var rowSpan = Math.max(2, Math.round((heightPx + gap) / (row + gap)));
        if (isFeat) rowSpan = Math.max(featH, rowSpan);
        it.style.gridRow = 'span ' + Math.max(1, rowSpan);
        var tileH = rowSpan * row + ((rowSpan - 1) * gap);
        it.style.height = tileH + 'px';
      }
    });
  }

  function layoutAll(){
    document.querySelectorAll('.ap-gallery--tiled').forEach(justifyLayout);
    document.querySelectorAll('.ap-gallery--collage').forEach(collageLayout);
  }

  var rid;
  window.addEventListener('resize', function(){ clearTimeout(rid); rid = setTimeout(layoutAll, 80); });
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', layoutAll); else layoutAll();
})();
JS;

        wp_register_script( self::HANDLE, false, [], '2.1.0', true );
        wp_add_inline_script( self::HANDLE, $js );
        wp_enqueue_script( self::HANDLE );
    }

    /** Query helper: only images from posts in the given category */
    private function get_image_ids_limited_to_category( $args ) {
        $category_slug = $args['category_slug'];
        $mode          = $args['mode'];         // 'post'|'attachment'
        $post_status   = $args['post_status'];  // array of post statuses
        $cycle         = $args['cycle'];        // 'none'|'hourly'|'daily'
        $max_items     = $args['max_items'];    // int

        // 1) Get the category and all its descendants (children, grandchildren, etc.)
        $category = get_category_by_slug( $category_slug );
        if ( ! $category ) return array();
        
        $category_ids = array( $category->term_id );
        
        // Get all descendant categories using get_terms with child_of parameter for reliability
        $descendants = get_terms( array(
            'taxonomy'   => 'category',
            'child_of'   => $category->term_id,
            'hide_empty' => false,
            'fields'     => 'ids'
        ) );
        
        if ( ! empty( $descendants ) && ! is_wp_error( $descendants ) ) {
            $category_ids = array_merge( $category_ids, $descendants );
        }

        // 2) Posts in the category and its children
        $post_query = new WP_Query( array(
            'post_type'           => 'post',
            'category__in'        => $category_ids,
            'post_status'         => $post_status,
            'posts_per_page'      => -1,
            'no_found_rows'       => true,
            'ignore_sticky_posts' => true,
            'fields'              => 'ids',
        ) );
        if ( empty( $post_query->posts ) ) return array();
        $post_ids = $post_query->posts;

        // 3) Collect attachment IDs
        $image_ids = array();
        if ( $mode === 'attachment' ) {
            $att_query = new WP_Query( array(
                'post_type'      => 'attachment',
                'post_status'    => 'inherit',
                'post_mime_type' => 'image',
                'posts_per_page' => -1,
                'no_found_rows'  => true,
                'fields'         => 'ids',
                'post_parent__in'=> $post_ids,
                'orderby'        => 'menu_order ID',
                'order'          => 'ASC',
            ) );
            if ( ! empty( $att_query->posts ) ) $image_ids = $att_query->posts;
        } else {
            foreach ( $post_ids as $pid ) {
                $tid = get_post_thumbnail_id( $pid );
                if ( ! $tid ) {
                    $children = get_children( array(
                        'post_parent'    => $pid,
                        'post_type'      => 'attachment',
                        'post_mime_type' => 'image',
                        'numberposts'    => 1,
                        'post_status'    => 'inherit',
                        'orderby'        => 'menu_order ID',
                        'order'          => 'ASC',
                        'fields'         => 'ids',
                    ) );
                    if ( $children ) $tid = (int) reset( $children );
                }
                if ( $tid ) $image_ids[] = $tid;
            }
        }

        if ( empty( $image_ids ) ) return array();

        // 4) Stable shuffle per cycle
        if ( $cycle !== 'none' ) {
            $seed = ( $cycle === 'hourly' ) ? gmdate( 'Y-m-d-H' ) : gmdate( 'Y-m-d' );
            $seed .= '|' . $category_slug . '|' . $mode;
            srand( crc32( $seed ) );
            shuffle( $image_ids );
            srand();
        }

        // 5) Cap
        return array_slice( $image_ids, 0, $max_items );
    }

    /** Multi-line shortcode alias: parse attributes from content body */
    public function shortcode_multiline( $atts, $content = null ) {
        $atts = is_array( $atts ) ? $atts : [];
        $content = is_null( $content ) ? '' : trim( $content );
        if ( $content !== '' ) {
            $joined = preg_replace( '/[\\r\\n]+/', ' ', $content );
            $parsed = shortcode_parse_atts( $joined );
            if ( is_array( $parsed ) ) $atts = array_merge( $atts, $parsed );
        }
        return $this->shortcode( $atts );
    }

    /** Main shortcode */
    public function shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'category_slug'  => '',
            'layout'         => 'tiled',     // tiled | grid | masonry | collage
            'per_row'        => '3',         // grid cols / masonry columns
            'gutter'         => '8',
            'v_gutter'       => '',
            'max'            => '12',
            'cycle'          => 'none',      // none | hourly | daily
            'include_draft'  => 'false',
            'click_menu'     => 'false',
            'mode'           => 'post',      // post | attachment
            'size'           => 'large',
            'link_thumbnail_to' => 'post',      // post | image
            'display_meta'   => '',          // EXIF metadata template

            // Tiled (advanced)
            'row_height'     => '220',
            'last_row'       => 'left',   // left | justify
            'tolerance'      => '0.25',   // 0.05–0.60
            'panorama_thresh'=> '2.6',
            'min_per_row'    => '2',
            'max_per_row'    => '5',

            // Collage (metro)
            'cols'           => '6',
            'row_unit'       => '12',
            'min_col'        => '2',
            'max_col'        => '3',
            'feature_every'  => '5',
            'feature_w'      => '3',
            'feature_h'      => '4',

            // Crop / background (collage)
            'crop'           => 'true',          // true|false
            'bg'             => 'transparent',   // letterbox background
        ), $atts, self::SHORTCODE );

        // Normalize & sanitize
        $category_slug  = sanitize_title( $atts['category_slug'] );
        $layout         = in_array( $atts['layout'], array('tiled','grid','masonry','collage'), true ) ? $atts['layout'] : 'tiled';
        $per_row        = max( 1, intval( $atts['per_row'] ) );
        $gutter         = max( 0, intval( $atts['gutter'] ) );
        $max_items      = max( 1, intval( $atts['max'] ) );
        $cycle          = in_array( $atts['cycle'], array( 'none', 'hourly', 'daily' ), true ) ? $atts['cycle'] : 'none';
        $include_draft  = filter_var( $atts['include_draft'], FILTER_VALIDATE_BOOLEAN );
        $click_menu     = filter_var( $atts['click_menu'], FILTER_VALIDATE_BOOLEAN );
        $mode           = ( $atts['mode'] === 'attachment' ) ? 'attachment' : 'post';
        $img_size       = sanitize_key( $atts['size'] );
        $link_thumbnail_to = ( $atts['link_thumbnail_to'] === 'image' ) ? 'image' : 'post';
        $display_meta   = trim( $atts['display_meta'] );

        // Tiled tuning
        $row_height     = max( 80, intval( $atts['row_height'] ) );
        $last_row       = ($atts['last_row'] === 'justify') ? 'justify' : 'left';
        $tolerance      = max(0.05, min(0.6, floatval($atts['tolerance'])));
        $pano_thresh    = max(1.8, floatval($atts['panorama_thresh']));
        $min_per_row    = max(1, intval($atts['min_per_row']));
        $max_per_row    = max($min_per_row, intval($atts['max_per_row']));

        // Collage tuning
        $cols           = max( 2, intval( $atts['cols'] ) );
        $row_unit       = max( 6, intval( $atts['row_unit'] ) );
        $min_col        = max( 1, intval( $atts['min_col'] ) );
        $max_col        = max( $min_col, intval( $atts['max_col'] ) );
        $feature_every  = max( 0, intval( $atts['feature_every'] ) );
        $feature_w      = max( $max_col, intval( $atts['feature_w'] ) );
        $feature_h      = max( 3, intval( $atts['feature_h'] ) );

        $crop           = filter_var( $atts['crop'], FILTER_VALIDATE_BOOLEAN );
        $bg             = sanitize_text_field( $atts['bg'] );

        // Determine statuses (for posts; attachments are 'inherit')
        // If include_draft=true, we must fetch images from non-published posts too
        // (linking is handled later per your rules).
        $post_status = array( 'publish' );
        if ( $include_draft ) {
            $post_status = array( 'publish', 'draft', 'pending', 'future', 'private' );
        }

        // Get image attachment IDs limited to posts in this category
        $image_ids = $this->get_image_ids_limited_to_category( array(
            'category_slug' => $category_slug,
            'mode'          => $mode,
            'post_status'   => $post_status,
            'cycle'         => $cycle,
            'max_items'     => $max_items,
        ) );
        if ( empty( $image_ids ) ) {
            return '<div class="ap-gallery ap-gallery--empty">No images found for this category.</div>';
        }

        // Wrapper classes & CSS vars
        $classes = array( 'ap-gallery', 'ap-gallery--' . $layout );
        $style   = '--ap-gap:' . $gutter . 'px;';

        $v_gutter = trim($atts['v_gutter']);
        if ($v_gutter !== '' && is_numeric($v_gutter)) {
            $style .= '--ap-vgap:' . intval($v_gutter) . 'px;';
        }


        if ( $layout === 'grid' ) {
            $style .= '--ap-grid-cols: repeat(' . $per_row . ', 1fr);';
        } elseif ( $layout === 'masonry' ) {
            $style .= '--ap-columns: ' . $per_row . ';';
        } elseif ( $layout === 'collage' ) {
            $style .= '--ap-cols:' . $cols . '; --ap-row:' . $row_unit . 'px;';
            if ( ! $crop ) { $classes[] = 'ap--nocrop'; }
            $style .= '--ap-bg:' . $bg . ';';
        }

        $html  = '<div class="' . esc_attr( implode(' ', $classes) ) . '" style="' . esc_attr( $style ) . '"';
        
        // Always add lightbox marker - thumbnails should always open in lightbox
        $html .= ' data-lightbox="true"';
        
        if ( $layout === 'tiled' ) {
            $html .= ' data-row-height="' . esc_attr( $row_height ) . '"';
            $html .= ' data-last-row="' . esc_attr( $last_row ) . '"';
            $html .= ' data-tolerance="' . esc_attr( $tolerance ) . '"';
            $html .= ' data-pano-threshold="' . esc_attr( $pano_thresh ) . '"';
            $html .= ' data-min-per-row="' . esc_attr( $min_per_row ) . '"';
            $html .= ' data-max-per-row="' . esc_attr( $max_per_row ) . '"';
        } elseif ( $layout === 'collage' ) {
            $html .= ' data-min-col="' . esc_attr($min_col) . '" data-max-col="' . esc_attr($max_col) . '"';
            $html .= ' data-feature-every="' . esc_attr($feature_every) . '" data-feature-w="' . esc_attr($feature_w) . '" data-feature-h="' . esc_attr($feature_h) . '"';
        }
        $html .= '>';

        // Render each image (each ID is an attachment)
        foreach ( $image_ids as $att_id ) {
            $parent_id   = (int) get_post_field( 'post_parent', $att_id );
            $post_status = ( $parent_id ) ? get_post_status( $parent_id ) : 'publish';
            
            // Determine link URL based on link_thumbnail_to attribute
            // For thumbnails, we always want lightbox behavior, so we use a placeholder href
            // The actual lightbox functionality is handled by JavaScript
            if ( $link_thumbnail_to === 'image' ) {
                $permalink = '#'; // Placeholder - lightbox will handle the actual image display
            } else {
                $permalink = $parent_id ? get_permalink( $parent_id ) : '#';
            }
            
            $title       = get_the_title( $parent_id ? $parent_id : $att_id );
            
            // Build caption from EXIF metadata if display_meta is set
            $caption = '';
            if ( ! empty( $display_meta ) ) {
                $caption = $this->build_caption_from_exif( $att_id, $display_meta );
            }

            $meta = wp_get_attachment_metadata( $att_id );
            $ar   = ( !empty($meta['width']) && !empty($meta['height']) && $meta['height'] > 0 )
                        ? ( $meta['width'] / $meta['height'] ) : 1.5;

            $img = wp_get_attachment_image( $att_id, $img_size, false, array(
                'class'    => 'ap-gallery__img',
                'loading'  => 'lazy',
                'decoding' => 'async',
            ) );

            $html .= '<figure class="ap-gallery__item" data-aspect="' . esc_attr( $ar ) . '" data-caption="' . esc_attr( $caption ) . '">';

            // --- Conditional linking rules ---
            $user_logged_in = is_user_logged_in();

            // For image links, always show the link (to the image file)
            // For post links, apply the original logic
            $should_link = false;
            if ( $link_thumbnail_to === 'image' ) {
                $should_link = true;
            } elseif ( $post_status === 'publish' || ( $post_status !== 'publish' && $user_logged_in ) ) {
                $should_link = true;
            }

            if ( $should_link ) {
                // Show as linked
                $link_attrs = 'class="ap-gallery__link ap-focus-outline" href="' . esc_url( $permalink ) . '" aria-label="' . esc_attr( $title ) . '"';
                
                // Always add the full image URL for lightbox functionality
                $full_image_url = wp_get_attachment_url( $att_id );
                $link_attrs .= ' data-full-image="' . esc_url( $full_image_url ) . '"';
                
                $html .= '  <a ' . $link_attrs . '>';
                $html .=        $img;
                $html .= '  </a>';
            } else {
                // Show image only, no link (unpublished post and visitor not logged in)
                $html .= $img;
            }

            // Optional click menu
            if ( $click_menu ) {
                $file_url = wp_get_attachment_url( $att_id );
                $post_permalink = $parent_id ? get_permalink( $parent_id ) : '';
                
                $html .= '  <span class="ap-gallery__menu-toggle" tabindex="0" aria-label="Open options"></span>';
                $html .= '  <div class="ap-gallery__menu" role="menu">';
                $html .= '    <span class="ap-gallery__menu-label">View:</span>';
                
                // Always use lightbox trigger for "Image" option - never expose direct image URLs
                $html .= '    <button role="menuitem" class="ap-gallery__lightbox-trigger" data-full-image="' . esc_url( $file_url ) . '">Image</button>';
                
                // Always show Post link if there's a parent post
                if ( $post_permalink && ( $post_status === 'publish' || ( $post_status !== 'publish' && $user_logged_in ) ) ) {
                    $html .= '    <span class="ap-gallery__menu-sep">|</span>';
                    $html .= '    <a role="menuitem" href="' . esc_url( $post_permalink ) . '">Post</a>';
                }
                
                $html .= '  </div>';
            }

            $html .= '</figure>';
        }

        $html .= '</div>';
        return $html;
    }
    
    /** Build caption from EXIF metadata template */
    private function build_caption_from_exif( $att_id, $template ) {
        // Get image metadata (includes EXIF)
        $meta = wp_get_attachment_metadata( $att_id );
        if ( empty( $meta ) || empty( $meta['image_meta'] ) ) {
            return '';
        }
        
        $exif = $meta['image_meta'];
        $file_path = get_attached_file( $att_id );
        
        // Get additional EXIF data not in WordPress metadata
        $exif_data = [];
        if ( function_exists( 'exif_read_data' ) && file_exists( $file_path ) ) {
            $raw_exif = @exif_read_data( $file_path );
            if ( $raw_exif ) {
                $exif_data = $raw_exif;
            }
        }
        
        // Build replacement array
        $replacements = [];
        
        // FileName
        $replacements['{FileName}'] = basename( $file_path );
        
        // Copyright - support default value syntax: {Copyright,Default Value}
        $copyright_value = ! empty( $exif['copyright'] ) ? $exif['copyright'] : 
                           ( ! empty( $exif_data['Copyright'] ) ? $exif_data['Copyright'] : '' );
        $replacements['{Copyright}'] = $copyright_value;
        
        // Handle Copyright with default value pattern
        if ( preg_match( '/\{Copyright,([^}]+)\}/', $template, $matches ) ) {
            $default_copyright = trim( $matches[1] );
            $copyright_final = ! empty( $copyright_value ) ? $copyright_value : $default_copyright;
            $template = str_replace( $matches[0], $copyright_final, $template );
        }
        
        // CameraMake
        $replacements['{CameraMake}'] = ! empty( $exif['camera'] ) ? $exif['camera'] : 
                                         ( ! empty( $exif_data['Make'] ) ? $exif_data['Make'] : '' );
        
        // CameraModel
        $replacements['{CameraModel}'] = ! empty( $exif_data['Model'] ) ? $exif_data['Model'] : '';
        
        // ISOSpeedRatings (format as ISO-100)
        $iso = ! empty( $exif_data['ISOSpeedRatings'] ) ? $exif_data['ISOSpeedRatings'] : '';
        $replacements['{ISOSpeedRatings}'] = $iso ? 'ISO-' . $iso : '';
        
        // DateTimeOriginal
        $replacements['{DateTimeOriginal}'] = ! empty( $exif['created_timestamp'] ) ? 
                                               date( 'Y-m-d H:i:s', $exif['created_timestamp'] ) : 
                                               ( ! empty( $exif_data['DateTimeOriginal'] ) ? $exif_data['DateTimeOriginal'] : '' );
        
        // FocalLength (convert fraction to mm, e.g., 380/10 = 38mm)
        $focal = ! empty( $exif['focal_length'] ) ? $exif['focal_length'] : 
                 ( ! empty( $exif_data['FocalLength'] ) ? $exif_data['FocalLength'] : '' );
        if ( $focal && strpos( $focal, '/' ) !== false ) {
            $parts = explode( '/', $focal );
            if ( count( $parts ) == 2 && $parts[1] != 0 ) {
                $focal = round( $parts[0] / $parts[1] ) . 'mm';
            }
        } elseif ( is_numeric( $focal ) ) {
            $focal = round( $focal ) . 'mm';
        }
        $replacements['{FocalLength}'] = $focal;
        
        // ShutterSpeedValue (convert to fraction, e.g., 7321928/1000000 = 1/136s)
        $shutter = ! empty( $exif['shutter_speed'] ) ? $exif['shutter_speed'] : 
                   ( ! empty( $exif_data['ExposureTime'] ) ? $exif_data['ExposureTime'] : '' );
        if ( $shutter && strpos( $shutter, '/' ) !== false ) {
            $parts = explode( '/', $shutter );
            if ( count( $parts ) == 2 && $parts[0] != 0 ) {
                $decimal = $parts[1] / $parts[0];
                if ( $decimal >= 1 ) {
                    $shutter = '1/' . round( $decimal ) . 's';
                } else {
                    $shutter = round( 1 / $decimal, 1 ) . 's';
                }
            }
        } elseif ( is_numeric( $shutter ) ) {
            if ( $shutter >= 1 ) {
                $shutter = round( $shutter, 1 ) . 's';
            } else {
                $shutter = '1/' . round( 1 / $shutter ) . 's';
            }
        }
        $replacements['{ShutterSpeedValue}'] = $shutter;
        
        // FNumber (format as f/8)
        $aperture = ! empty( $exif['aperture'] ) ? $exif['aperture'] : 
                    ( ! empty( $exif_data['FNumber'] ) ? $exif_data['FNumber'] : '' );
        if ( $aperture && strpos( $aperture, '/' ) !== false ) {
            $parts = explode( '/', $aperture );
            if ( count( $parts ) == 2 && $parts[1] != 0 ) {
                $aperture = 'f/' . round( $parts[0] / $parts[1], 1 );
            }
        } elseif ( is_numeric( $aperture ) ) {
            $aperture = 'f/' . $aperture;
        }
        $replacements['{FNumber}'] = $aperture;
        
        // GPSLatitude (format as 37° 13′ 0″)
        $gps_lat = '';
        if ( ! empty( $exif_data['GPSLatitude'] ) && ! empty( $exif_data['GPSLatitudeRef'] ) ) {
            $gps_lat = $this->format_gps_coordinate( $exif_data['GPSLatitude'], $exif_data['GPSLatitudeRef'] );
        }
        $replacements['{GPSLatitude}'] = $gps_lat;
        
        // GPSLongitude (format as 112° 59′ 0″)
        $gps_lon = '';
        if ( ! empty( $exif_data['GPSLongitude'] ) && ! empty( $exif_data['GPSLongitudeRef'] ) ) {
            $gps_lon = $this->format_gps_coordinate( $exif_data['GPSLongitude'], $exif_data['GPSLongitudeRef'] );
        }
        $replacements['{GPSLongitude}'] = $gps_lon;
        
        // LensManufacturer
        $replacements['{LensManufacturer}'] = ! empty( $exif_data['UndefinedTag:0xA433'] ) ? 
                                               $exif_data['UndefinedTag:0xA433'] : '';
        
        // LensModel
        $replacements['{LensModel}'] = ! empty( $exif_data['UndefinedTag:0xA434'] ) ? 
                                        $exif_data['UndefinedTag:0xA434'] : '';
        
        // Replace placeholders in template
        $caption = str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
        
        // Clean up: remove empty placeholders and extra separators
        $caption = preg_replace( '/\{[^}]+\}/', '', $caption ); // Remove unfilled placeholders
        $caption = preg_replace( '/\s*\|\s*\|/', ' |', $caption ); // Remove double separators
        $caption = preg_replace( '/^\s*\|\s*/', '', $caption ); // Remove leading separator
        $caption = preg_replace( '/\s*\|\s*$/', '', $caption ); // Remove trailing separator
        $caption = trim( $caption );
        
        return $caption;
    }
    
    /** Format GPS coordinates to degrees, minutes, seconds */
    private function format_gps_coordinate( $coordinate, $ref ) {
        if ( ! is_array( $coordinate ) || count( $coordinate ) < 3 ) {
            return '';
        }
        
        $degrees = $this->gps_fraction_to_number( $coordinate[0] );
        $minutes = $this->gps_fraction_to_number( $coordinate[1] );
        $seconds = $this->gps_fraction_to_number( $coordinate[2] );
        
        return round( $degrees ) . '° ' . round( $minutes ) . '′ ' . round( $seconds ) . '″ ' . $ref;
    }
    
    /** Convert GPS fraction to decimal number */
    private function gps_fraction_to_number( $fraction ) {
        if ( strpos( $fraction, '/' ) !== false ) {
            $parts = explode( '/', $fraction );
            if ( count( $parts ) == 2 && $parts[1] != 0 ) {
                return $parts[0] / $parts[1];
            }
        }
        return floatval( $fraction );
    }
}

new AP_Category_Image_Gallery();