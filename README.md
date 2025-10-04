# Category Image Gallery

**Category Image Gallery** is a lightweight, flexible WordPress plugin for displaying image galleries automatically generated from post categories.  
It supports multiple layout styles (tiled, collage, grid, masonry), randomization, optional inclusion of draft posts, and dynamic image linking behavior that respects publication state and user login status.

---

## ✨ Features

- 📂 **Category-based galleries** — Automatically pulls featured images or attachments from posts in a chosen category.  
- 🧩 **Multiple layouts** —  
  - `tiled` (Jetpack-style justified rows)  
  - `collage` (metro-style mosaic)  
  - `grid` and `masonry` (optional)  
- 🔁 **Randomized selection** when there are more images than the specified max.  
- 🕐 **Cycle interval** options (`daily`, `hourly`, etc.) to refresh randomized images.  
- 🧱 **Custom row heights, gutter spacing, panorama detection**, and intelligent last-row balancing for the `tiled` layout.  
- 🧑‍💻 **Draft-aware rendering** —  
  - Published posts → image links to post.  
  - Draft/private posts (visitor) → image shown, **no link**.  
  - Draft/private posts (logged-in user) → image links to post.  
- 🖼️ **Click-menu option** to choose between viewing the image or reading its post.  
- ⚡ **Responsive, lazy-loaded**, and **SEO-friendly** images.  
- 🔒 Works with both published and unpublished (private) content when desired.

---

## 🧠 Usage

Insert the shortcode in any post, page, or block:

```text
[category_image_gallery
  category_slug="infrared-photography"
  layout="tiled"
  row_height="230"
  tolerance="0.3"
  panorama_thresh="2.6"
  min_per_row="2"
  max_per_row="6"
  last_row="left"
  gutter="4"
  max="20"
  include_draft="true"
  click_menu="true"]
```

### Key Shortcode Attributes

| Attribute | Description |
|------------|--------------|
| `category_slug` | Slug of the category to pull images from. |
| `layout` | `tiled`, `collage`, `grid`, or `masonry`. |
| `row_height` | Base height (px) for tiled rows. |
| `tolerance` | Flex factor (0–0.6). Larger = smoother row balancing. |
| `panorama_thresh` | Aspect ratio (e.g. 2.6) at which an image fills a full row. |
| `min_per_row`, `max_per_row` | Soft bounds on images per row. |
| `last_row` | `left` (not stretched) or `justify`. |
| `gutter` | Horizontal gap between images (px). |
| `v_gutter` | Optional vertical gap (defaults to same as `gutter`). |
| `max` | Maximum number of images to show. |
| `include_draft` | Include unpublished posts. Non-logged-in visitors see image only; logged-in users can click to the post. |
| `click_menu` | Adds a small dropdown menu when clicking an image. |
| `cycle` | `hourly`, `daily`, or blank — randomize refresh frequency. |

---

## 🧱 Layout Examples

**Tiled (Jetpack-style justified layout)**  
Balances rows automatically based on image aspect ratios.

**Collage (Metro layout)**  
Images span variable columns and rows; excellent for IR or travel galleries.

---

## 🔐 Draft and Login Logic

| Post Status | Logged-In User | Link | Visible |
|--------------|----------------|------|----------|
| Published | ✅ | ✅ |
| Draft / Private | ✅ | ✅ |
| Draft / Private | 🚫 | ✅ (no link) |

---

## 🧰 Installation

1. Copy the plugin folder into `/wp-content/plugins/category-image-gallery/`.  
2. Activate **Category Image Gallery** from your WordPress admin → *Plugins*.  
3. Use the shortcode in any post, page, or block.

---

## ⚙️ Developer Notes

- Images are fetched via `WP_Query` based on the category and parent post status.  
- When `include_draft="true"`, attachments from unpublished posts are included in the gallery.  
- Visibility and linking behavior are determined at render time based on `get_post_status()` and `is_user_logged_in()`.  
- The JavaScript portion performs live layout adjustments for justified (tiled) and collage modes.  
- CSS variables (`--ap-gap`, `--ap-vgap`, `--ap-cols`, etc.) make the layout theme-friendly and easily overridable.

---

## 🧩 Example Gallery (Infrared Photography)
```text
[category_image_gallery
  category_slug="infrared-photography"
  layout="tiled"
  row_height="210"
  tolerance="0.35"
  panorama_thresh="2.4"
  min_per_row="2"
  max_per_row="6"
  last_row="left"
  gutter="4"
  include_draft="true"]
```

---

## 🪪 License

This project is licensed under the **MIT License**:

```
MIT License

Copyright (c) 2025 Richard Cox

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
```

---

## 🧑‍💻 Contributing

Pull requests are welcome!  
If you’d like to:
- Add new layout types (e.g. `masonry` enhancements)
- Improve performance
- Add shortcode options or filters

Please fork the repository and submit a PR describing your changes.

---

## 🧩 WordPress Plugin Header

```php
/**
 * Plugin Name: AP Category Image Gallery
 * Plugin URI:  https://github.com/richardcox/Category-Image-Gallery
 * Description: Category-based WordPress image gallery with Jetpack-style tiled layout, collage grid, and draft-aware logic.
 * Version:     1.8.2
 * Author:      Richard Cox
 * Author URI:  https://alwaysphotographing.com
 * License:     MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: ap-category-image-gallery
 */
```

---

## 📦 Composer.json Example

```json
{
  "name": "alwaysphotographing/category-image-gallery",
  "description": "A WordPress plugin for dynamic category-based image galleries with tiled, collage, grid, and masonry layouts.",
  "type": "wordpress-plugin",
  "license": "MIT",
  "authors": [
    {
      "name": "Richard Cox",
      "homepage": "https://alwaysphotographing.com"
    }
  ],
  "require": {
    "php": ">=7.4"
  }
}
```
