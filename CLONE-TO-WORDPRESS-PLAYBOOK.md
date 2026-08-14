# Playbook: Biến website clone (wget) thành theme WordPress

> File này mô tả toàn bộ quy trình và kiến trúc đã dùng để biến bản clone tĩnh
> (wget) của một website Shopify thành theme WordPress + WooCommerce hoạt động
> đầy đủ (đây chính là theme `vuzix-practice`). Copy file này vào gốc dự án mới
> (đổi tên thành `CLAUDE.md` nếu muốn AI tự động đọc) để AI thực hiện đúng quy
> trình này mà không cần giải thích lại từ đầu.

## 1. Bối cảnh & mục tiêu

Quy trình làm việc chuẩn:

1. Dùng `wget` (hoặc công cụ tương đương) clone toàn bộ website nguồn (HTML,
   CSS, JS, ảnh) về máy — giữ nguyên cấu trúc file `.html` tĩnh.
2. Đưa các file HTML đó vào một theme WordPress mới, đặt tên theo quy ước
   `original-*` (xem mục 2).
3. Xây functions.php + template PHP để **trích xuất phần nội dung tĩnh** từ
   các file `original-*.html` này, ghép với phần **dữ liệu động** của WordPress
   (WooCommerce sản phẩm, blog post, form...), thay vì viết lại giao diện từ
   đầu bằng tay.
4. Việc "hoàn thiện" theme = làm cho phần tĩnh (giao diện, style, cấu trúc
   HTML gốc) hoạt động đúng bên trong WordPress, với dữ liệu thật chảy vào
   đúng chỗ, đường link/asset trỏ đúng, và toàn bộ nội dung được dịch sang
   tiếng Việt.

Lợi ích của cách này: giữ được 100% giao diện/CSS gốc của site nguồn, không
cần dựng lại theme từ con số 0, rủi ro sai lệch UI thấp.

## 2. Quy ước đặt tên & cấu trúc thư mục

Sau khi clone xong, tổ chức file HTML tĩnh theo các thư mục sau ngay trong
theme:

```
theme-folder/
  original-index.html          <- trang chủ (home)
  original-cart.html           <- trang giỏ hàng (nếu có)
  original-pages/              <- các trang tĩnh (about, contact, policy...)
  original-products/           <- trang chi tiết từng sản phẩm
  original-collections/        <- trang danh mục sản phẩm (category/shop)
  original-blogs/               <- bài viết blog, có thể có thư mục con
    case-studies/
    release-notes/
    white-papers/
  original-policies/            <- một số site tách riêng trang chính sách
  original-tools/               <- các mini-tool/app nhúng riêng (nếu có)
```

Tên file bên trong mỗi thư mục = đúng slug của trang trên site gốc
(`contact-sales.html`, `4800mah-extended-use-power-bank.html`...). Đây là
mấu chốt để hàm tìm-file-theo-slug (mục 4) hoạt động tự động, không cần khai
báo map thủ công cho từng trang.

## 3. Pipeline xử lý 1 khối HTML tĩnh

Mọi đoạn HTML tĩnh trước khi render ra trình duyệt đều phải đi qua đúng chuỗi
xử lý này (thứ tự quan trọng):

1. `vuzix_strip_shopify_runtime_assets($html)` — xoá các script/app runtime
   của nền tảng gốc không còn hoạt động ngoài môi trường gốc (Shopify apps,
   checkout script, analytics...). Nếu không xoá, các script này sẽ gây lỗi
   console 404/502 vì gọi API không tồn tại.
2. `vuzix_convert_internal_links($html)` — chuyển link nội bộ kiểu site gốc
   (`pages/xxx.html`, `products/xxx.html`, `collections/xxx.html`,
   `blogs/xxx.html`) thành URL permalink thật của WordPress/WooCommerce.
3. `vuzix_replace_asset_paths($html, $theme_uri)` — chuyển đường dẫn ảnh/CSS/JS
   tương đối hoặc trỏ CDN gốc thành đường dẫn asset trong theme
   (`get_template_directory_uri()`).
4. `vuzix_translate_static_ui($html)` — dịch toàn bộ text tĩnh sang tiếng
   Việt bằng một dictionary exact-match lớn (xem mục 6). **Luôn chạy bước
   này SAU CÙNG**, sau khi link/asset đã đúng.

Viết 1 hàm dùng chung `vuzix_get_main_content($original_filename)` thực hiện
đúng 4 bước trên cho phần `<main id="MainContent">...</main>` trích từ 1 file
`original-*.html`, và tái sử dụng hàm này ở mọi nơi cần lấy nội dung tĩnh
(page.php, front-page.php, woocommerce/single-product.php...).

## 4. Header / Footer / Page templates

- **header.php**: lấy toàn bộ nội dung TRƯỚC thẻ `<main id="MainContent"`
  trong `original-index.html`, chạy qua pipeline ở mục 3, rồi in ra — chèn
  `wp_head()` ngay trước `</head>` và `wp_body_open()` ngay sau `<body>`.
- **footer.php**: lấy toàn bộ nội dung SAU `</main>` trong `original-index.html`,
  chạy qua pipeline, in ra — chèn `wp_footer()` ngay trước `</body>`.
- **front-page.php**: `echo vuzix_get_main_content('original-index.html');`
- **page.php**: lấy slug trang hiện tại (`get_post_field('post_name', ...)`),
  tìm file khớp bằng `vuzix_find_original_file($slug)` (dò qua danh sách thư
  mục ở mục 2, khớp `$slug.html`), rồi `echo vuzix_get_main_content($file)`.
  Nếu không tìm thấy file tương ứng → fallback dùng `the_content()` của WP
  editor bình thường.
- **archive.php / blog.php / single.php**: dùng cho danh sách/bài blog — có
  thể lấy layout tĩnh làm khung rồi render post WordPress động bên trong,
  hoặc map file `original-blogs/<slug>.html` như page.php nếu blog cũ được
  giữ y nguyên dạng tĩnh.

## 5. Tích hợp WooCommerce (nếu site có bán hàng)

1. **Import sản phẩm**: export CSV từ site gốc (hoặc tự trích xuất), map
   đúng cột chuẩn WooCommerce (`Name`, `SKU`, `Categories`, `Regular price`,
   `Images`...), import qua WooCommerce > Products > Import.
2. **woocommerce/single-product.php**: dùng `vuzix_get_main_content()` lấy
   layout tĩnh của đúng file sản phẩm tương ứng (map theo SKU/slug), sau đó
   dùng `preg_replace`/`preg_replace_callback` để thay các phần **tĩnh** trong
   HTML gốc (tiêu đề, giá, mô tả, ảnh, nút mua) bằng dữ liệu **động** thật từ
   `$product` (WC_Product) và `woocommerce_template_single_add_to_cart()`.
   Nếu không tìm thấy file tĩnh khớp → fallback dựng 1 layout tối giản bằng
   PHP thuần (hero + gallery + add-to-cart) để không bao giờ trắng trang.
3. **woocommerce/archive-product.php**: dựng lại layout danh mục sản phẩm từ
   file `original-collections/*.html`, nhưng danh sách sản phẩm render động
   bằng vòng lặp WooCommerce chuẩn (`woocommerce_product_loop()`,
   `wc_get_template_part('content', 'product')`). Tiêu đề trang lấy từ
   `single_term_title()` — **chú ý**: tên category lưu trong DB WooCommerce
   thường vẫn là tiếng Anh (import từ CSV), cần có bảng dịch riêng cho tiêu
   đề category (không đi qua được `vuzix_translate_static_ui` vì đây là dữ
   liệu động, không phải text tĩnh trong file HTML).
4. **Checkout tối giản** (nếu chỉ cần "đặt hàng, sales liên hệ lại" thay vì
   thanh toán online thật):
   - Đăng ký 1 payment gateway thủ công (`WC_Payment_Gateway` con), khi
     submit chỉ `update_status('on-hold')`, không thu tiền.
   - Dùng filter `woocommerce_checkout_fields` để tối giản field + đổi thứ
     tự/độ bắt buộc theo yêu cầu thực tế (ví dụ: SĐT bắt buộc, email/tên
     không bắt buộc).
   - Validate field bắt buộc phía server luôn (hook
     `woocommerce_after_checkout_validation`) — đừng tin riêng JS phía client.
5. **Bật gửi mail bất đồng bộ**: thêm
   `add_filter('woocommerce_defer_transactional_emails', '__return_true');`
   — nếu không, checkout sẽ bị chậm vì phải chờ SMTP gửi xong mail mới trả
   kết quả về trình duyệt (xem thêm mục 8).
6. **Email tùy biến giao diện riêng**: override
   `woocommerce/emails/customer-*.php` và `woocommerce/emails/admin-*.php`
   trong theme — viết HTML email độc lập hoàn toàn (không cần gọi
   `woocommerce_email_header`/`_footer` mặc định), lấy dữ liệu từ biến
   `$order`, `$email_heading`, `$email` mà WooCommerce truyền vào.

## 6. Quy trình dịch toàn site sang tiếng Việt

Cơ chế: 1 dictionary exact-match lớn trong `functions.php`
(`$labels = array( 'English text' => 'Bản dịch tiếng Việt', ... )`), áp dụng
bằng regex tìm text nằm trực tiếp giữa 2 thẻ (`>text<`) và thay thế.

**Quy trình quét & dịch hàng loạt** (dùng khi số trang nhiều):

1. Viết script quét tất cả file `original-*.html` (dùng parser HTML thật,
   ví dụ Python `BeautifulSoup`, KHÔNG dùng regex thô để tách text — dễ bỏ
   sót/nhầm do HTML lồng nhau phức tạp).
2. Trích toàn bộ text node còn là tiếng Anh (bỏ qua nội dung trong
   `<script>`, `<style>`), gom theo từng file.
3. Chia thành nhiều batch, giao cho các agent dịch song song (mỗi agent trả
   về 1 dictionary JSON `{ "English": "Tiếng Việt" }`), tổng hợp lại rồi merge
   một lượt vào `$labels`.
4. Merge xong, quét lại để xác nhận không còn sót text tiếng Anh nào ngoài
   danh sách cố tình bỏ qua (mục 7).

**Những cạm bẫy đã gặp — cần xử lý ngay từ đầu, đỡ phải sửa lại sau:**

- **Khoảng trắng/xuống dòng giữa câu**: file HTML export gốc hay bị ngắt
  dòng cứng giữa câu (`"...improving image\nprivacy and..."`). Nếu so khớp
  dictionary bằng regex đòi hỏi khớp tuyệt đối từng khoảng trắng, những đoạn
  này sẽ KHÔNG được dịch. Khi build pattern so khớp, phải coi mọi chuỗi
  khoảng trắng liên tiếp (`\s+`) là tương đương nhau, không so khớp `\s*`
  đúng nguyên văn.
- **`&` vs `&amp;`**: cùng 1 câu có thể xuất hiện ở nhiều trang với 1 nơi
  dùng `&` thường, nơi khác dùng entity `&amp;` (do cách export khác nhau).
  Cần có bước quét chủ động: với mỗi key dictionary có `&amp;`, kiểm tra xem
  biến thể `&` thường có tồn tại trong site mà chưa có bản dịch không.
- **Double-encode**: cẩn thận khi build dictionary bằng script tự động, dễ
  bị lỗi mã hoá lặp (`&amp;amp;` thay vì `&amp;`) khiến key không bao giờ
  khớp được — luôn xác minh lại với nội dung HTML gốc thật trước khi tin vào
  1 key trong dictionary đã đúng.
- **Từ ngắn/chung chung** (`Enter`, `Email`, `This`, `Save`...): rất rủi ro
  nếu thêm vào dictionary vì exact-match áp dụng toàn site — 1 từ đúng ngữ
  cảnh ở trang này có thể sai hoàn toàn ở trang khác. Cân nhắc kỹ, và loại
  trừ khỏi các batch dịch tự động nếu từ đó xuất hiện lặp lại ở nhiều ngữ
  cảnh khác nhau trong site.
- **Trang có text bị tách rời từng chữ** (do site gốc convert từ Word/PDF,
  để lại các `<span></span>` rỗng chen giữa mỗi từ): không dịch máy trực
  tiếp theo từng fragment — phải gộp lại thành câu hoàn chỉnh trước, dịch,
  rồi mới ghép lại (không thể dùng chung batch với các trang bình thường).
- **Phạm vi cố tình bỏ qua dịch**: xác nhận rõ với người yêu cầu ngay từ đầu
  những phần KHÔNG dịch (ví dụ: trang blog nội dung dài, trang chi tiết sản
  phẩm, tên thương hiệu/tên riêng, danh sách tên quốc gia trong bộ chọn khu
  vực, mã sản phẩm/tên kỹ thuật viết tắt).

## 7. Hiệu năng — làm ngay từ đầu, đừng để cuối cùng

Đây là bài học quan trọng nhất: dictionary dịch càng lớn (hàng nghìn dòng),
việc chạy lại toàn bộ regex so khớp trên MỌI request (header, main content,
footer — tức 3 lần/trang) mà không cache sẽ khiến site cực kỳ chậm (điểm
Performance Lighthouse có thể tụt xuống dưới 40).

**Bắt buộc làm ngay khi dictionary vượt quá vài trăm dòng** (đừng đợi user
phàn nàn mới sửa):

```php
function vuzix_cached_html_pipeline( $cache_key, $source_paths, $callback ) {
    $version = '';
    foreach ( $source_paths as $path ) {
        $version .= '|' . ( file_exists( $path ) ? filemtime( $path ) : '0' );
    }
    $transient_key = 'vuzix_html_' . md5( $cache_key . $version );
    $cached = get_transient( $transient_key );
    if ( false !== $cached ) {
        return $cached;
    }
    $output = call_user_func( $callback );
    set_transient( $transient_key, $output, WEEK_IN_SECONDS );
    return $output;
}
```

Bọc quanh toàn bộ pipeline (header, footer, `vuzix_get_main_content()`) bằng
hàm này, dùng `filemtime()` của file HTML gốc + của `functions.php` (nơi
khai báo dictionary) làm 1 phần cache key — cache tự làm mới khi có thay đổi,
không cần xoá thủ công.

## 8. Checkout / email chậm — nguyên nhân thường gặp

Nếu sau khi đặt hàng, trang "đặt hàng thành công" load lâu mới hiện: rất có
thể do WooCommerce đang gửi mail (khách hàng + admin) đồng bộ ngay trong lúc
xử lý request, trình duyệt phải chờ SMTP gửi xong. Bật tính năng gửi mail
bất đồng bộ có sẵn của WooCommerce (không cần tự viết queue):

```php
add_filter( 'woocommerce_defer_transactional_emails', '__return_true' );
```

## 9. Checklist hoàn thiện cuối cùng

- [ ] Tất cả link nội bộ trỏ đúng URL WordPress (không còn link `.html` kiểu
      site gốc).
- [ ] Tất cả asset (ảnh/CSS/JS) load được, không còn trỏ ra domain/CDN gốc.
- [ ] Không còn script/app runtime của nền tảng gốc gây lỗi console.
- [ ] Toàn site đã dịch tiếng Việt (trừ phạm vi đã thống nhất bỏ qua) — quét
      lại bằng script tự động, đừng chỉ test bằng mắt.
- [ ] Tiêu đề category/tag động (không nằm trong file HTML tĩnh) đã có bảng
      dịch riêng.
- [ ] Đã bật cache cho pipeline dịch/link/asset — đo thử bằng
      `curl -o /dev/null -s -w "%{time_total}\n" <url>` load 2 lần liên tiếp,
      lần 2 phải nhanh hơn rõ rệt.
- [ ] Checkout (nếu có) đặt hàng xong không bị treo/chậm.
- [ ] Email xác nhận (khách hàng + admin/sales) đúng giao diện, đúng dữ
      liệu thật, đúng địa chỉ liên hệ (kiểm tra kỹ `admin_email` của
      WordPress so với email "From" thật đã cấu hình trong WooCommerce —
      hai giá trị này thường KHÁC NHAU, đừng nhầm).
- [ ] Chạy Lighthouse (Performance/Accessibility/SEO) kiểm tra lại sau khi
      cache đã "ấm" (load thử 1 lần trước khi đo).

## 10. Cách dùng file này cho dự án mới

1. Copy file này vào gốc theme/dự án WordPress mới.
2. Đưa cho AI kèm yêu cầu: "Làm theo đúng playbook trong file này để biến
   bản clone wget vừa xong thành theme WordPress hoàn chỉnh."
3. Chỉ cần bổ sung thêm những thông tin **đặc thù riêng của site mới** (ví
   dụ: site không dùng WooCommerce, hoặc có thêm loại trang đặc biệt khác)
   — phần quy trình chung không cần lặp lại.
