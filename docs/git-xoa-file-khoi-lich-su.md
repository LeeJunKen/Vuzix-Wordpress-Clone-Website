# Gỡ file đã lỡ commit ra khỏi Git (và khỏi lịch sử)

Ghi lại quy trình đã dùng để gỡ `vuzix-products-export.csv`, `vuzix-products-import.csv`, `vuzix-blog-export.xml` ra khỏi repo — để lần sau gặp file tương tự (file dữ liệu, file export, file nhạy cảm... lỡ commit) thì tự làm lại được.

## Có 2 mức độ khác nhau, phải phân biệt trước khi làm

| Tình huống | Cách xử lý |
|---|---|
| File chỉ mới nằm ở **commit mới nhất** (hoặc đang staged) | Chỉ cần gỡ khỏi index + thêm `.gitignore` + commit lại |
| File đã nằm trong **nhiều commit cũ** (đã commit từ lâu, qua nhiều lần sửa) | Phải viết lại (rewrite) toàn bộ các commit chứa file đó — phức tạp và rủi ro hơn hẳn |

Việc đầu tiên luôn phải làm là xác định mình đang ở tình huống nào, và **file đó đã push lên GitHub chưa** — vì rewrite lịch sử chỉ an toàn khi CHƯA push.

---

## Bước 0 — Luôn kiểm tra trước khi động vào git history

```bash
# Xem còn thay đổi gì chưa commit không (bắt buộc phải sạch trước khi rewrite history)
git status --short

# Xem file đó nằm trong (những) commit nào
git log --oneline -- duong/dan/file.csv

# Đồng bộ thông tin remote mới nhất
git fetch origin ten-branch

# Xem local đang "ahead" remote bao nhiêu commit — đây là các commit CHƯA PUSH,
# rewrite những commit này là AN TOÀN vì GitHub chưa hề biết đến chúng
git log --oneline origin/ten-branch..ten-branch
```

> ⚠️ Nếu file cần gỡ nằm trong một commit **đã push** rồi (không thuộc danh sách "ahead" ở trên) thì rewrite history sẽ đổi SHA của những commit đó → khi push lại phải `git push --force` → **rất nguy hiểm nếu có người khác đã pull/clone repo**, vì họ sẽ bị lệch lịch sử hoàn toàn. Trường hợp đó cần bàn với team trước, không tự ý force-push.

---

## Trường hợp 1 (đơn giản): file mới nằm ở commit gần nhất

Đây là bước đã làm với `vuzix-products-export.csv` lúc mới bị lộ ra (chỉ nằm trong 1 commit vừa tạo):

```bash
# 1. Thêm pattern vào .gitignore để git không track lại file này nữa
#    (sửa file .gitignore, thêm dòng "*.csv")

# 2. Gỡ khỏi git nhưng GIỮ NGUYÊN file thật trên máy
git rm --cached vuzix-products-export.csv vuzix-products-import.csv

# 3. Stage luôn phần sửa .gitignore
git add .gitignore

# 4. Kiểm tra lại đúng những gì sẽ commit (không dính nhầm file khác đang sửa dở)
git status --short
git diff --staged --stat

# 5. Commit
git commit -m "Untrack CSV export files and ignore *.csv going forward"
```

Sau bước này: bản mới nhất (HEAD) đã sạch, nhưng **các commit CŨ hơn** (nếu file từng được thêm ở đó) vẫn còn chứa file này trong lịch sử — nếu push, ai xem lại commit cũ trên GitHub vẫn thấy được. Muốn xoá luôn dấu vết đó thì phải làm Trường hợp 2.

---

## Trường hợp 2 (triệt để): xoá file khỏi TOÀN BỘ lịch sử commit

Áp dụng khi phát hiện file còn nằm ở một commit cũ hơn nữa (ví dụ `vuzix-products-import.csv` hoá ra được thêm từ 6 commit trước đó, không phải commit gần nhất).

### 2.1 Xác nhận phạm vi các commit cần sửa (phải là commit CHƯA PUSH)

```bash
git fetch origin dev
git log --oneline origin/dev..dev
```

Toàn bộ danh sách hiện ra ở đây là các commit local sẽ bị đổi SHA — xác nhận chưa có commit nào trong đó đã lên GitHub.

### 2.2 Dọn sạch working tree (bắt buộc, filter-branch không chạy được nếu còn thay đổi chưa commit)

```bash
git status --short
git stash push -u -m "wip truoc khi filter-branch"
```

### 2.3 Chạy filter-branch, chỉ xử lý đúng đoạn commit chưa push

```bash
FILTER_BRANCH_SQUELCH_WARNING=1 git filter-branch --force --index-filter \
  "git rm --cached --ignore-unmatch vuzix-products-import.csv vuzix-products-export.csv vuzix-blog-export.xml" \
  --prune-empty -- origin/dev..HEAD
```

Giải thích:
- `--index-filter` — chỉnh sửa trực tiếp trên index của từng commit (nhanh), không phải checkout ra ổ đĩa từng commit một (`--tree-filter` chậm hơn nhiều).
- `git rm --cached --ignore-unmatch ...` — gỡ đúng những file cần xoá ở MỖI commit; `--ignore-unmatch` để không lỗi ở các commit không có file đó.
- `--prune-empty` — nếu có commit nào sau khi xoá file trở thành rỗng hoàn toàn thì tự bỏ luôn commit đó.
- `-- origin/dev..HEAD` — **chỉ** áp dụng cho các commit chưa push, không đụng gì tới lịch sử cũ hơn (đã push).

### 2.4 Khôi phục lại phần đang làm dở

```bash
git stash pop
```

### 2.5 Kiểm tra lại cho chắc

```bash
# Phải ra kết quả TRỐNG — nghĩa là nhánh dev (thứ sẽ được push) không còn dính file nào
git log --oneline dev -- vuzix-products-import.csv vuzix-products-export.csv vuzix-blog-export.xml

# Message/số lượng các commit khác vẫn giữ nguyên, chỉ đổi SHA
git log --oneline origin/dev..dev
```

> Lưu ý: nếu file đó vẫn đang được **track ở commit gần nhất** trước khi chạy filter-branch, sau khi rewrite xong, git sẽ tự đồng bộ lại working directory theo đúng commit mới (giống hệt `git checkout`) — nghĩa là **file thật trên ổ đĩa cũng bị xoá luôn**, không chỉ gỡ khỏi git. Nếu vẫn muốn giữ file trên máy (chỉ không muốn nó nằm trong git), phải copy lại file đó vào thư mục sau khi filter-branch chạy xong.

### 2.6 Dọn dẹp phần thừa filter-branch để lại

`git filter-branch` tự tạo 1 ref backup (`refs/original/refs/heads/dev`) để lỡ có sai còn khôi phục được. Sau khi đã kiểm tra ổn thì dọn cho gọn:

```bash
git update-ref -d refs/original/refs/heads/dev
git reflog expire --expire=now --all
git gc --prune=now --quiet
```

(Ref backup này chỉ tồn tại local, không tự bị push lên GitHub — bỏ qua bước dọn này cũng không sao, chỉ là để repo local gọn hơn.)

### 2.7 Push

```bash
git push origin dev
```

Vì các commit này local chưa từng có trên GitHub, push bình thường, **không cần `--force`**.

---

## Cheat-sheet nhanh (copy dùng luôn)

```bash
# Kiểm tra trước
git status --short
git log --oneline -- ten-file
git fetch origin dev && git log --oneline origin/dev..dev

# Nếu file chỉ ở commit mới nhất
git rm --cached ten-file
echo "ten-file" >> .gitignore   # hoặc pattern *.ext
git add .gitignore
git commit -m "Untrack ten-file"

# Nếu file nằm sâu trong nhiều commit CHƯA PUSH
git stash push -u -m "wip"
FILTER_BRANCH_SQUELCH_WARNING=1 git filter-branch --force --index-filter \
  "git rm --cached --ignore-unmatch ten-file" \
  --prune-empty -- origin/dev..HEAD
git stash pop
# (copy lại file vào thư mục nếu bị xoá theo và vẫn muốn giữ trên máy)
git update-ref -d refs/original/refs/heads/dev
git reflog expire --expire=now --all
git gc --prune=now --quiet
git push origin dev
```

## Nếu lỡ file đã PUSH lên GitHub rồi

Cheat-sheet ở trên **không dùng được an toàn** trong trường hợp này vì sẽ phải force-push, đổi SHA của commit người khác có thể đã pull về. Cần:
1. Báo cho những người đang cùng làm việc trên repo biết trước.
2. Vẫn dùng `git filter-branch` (hoặc tốt hơn là cài `git filter-repo`) nhưng chạy trên toàn bộ lịch sử thay vì giới hạn `origin/dev..HEAD`.
3. `git push --force` (hoặc `--force-with-lease` để an toàn hơn).
4. Mọi người khác phải re-clone lại hoặc `git reset --hard origin/dev` sau khi pull.
5. Nếu dữ liệu nhạy cảm (mật khẩu, API key...) đã từng lộ ra remote — coi như đã bị lộ vĩnh viễn (ai đó có thể đã fetch trước khi xoá), phải đổi/thu hồi thông tin đó, xoá lịch sử chỉ là dọn dẹp chứ không "an toàn hoá" lại được.
