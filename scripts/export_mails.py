#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Script xuất lịch sử email nhận từ TMail Dockerized ra file
Hỗ trợ định dạng: JSON, CSV (Excel UTF-8 BOM), HTML archive, ZIP
Bản quyền (c) 2026 - CÔNG TY TNHH GIẢI PHÁP SỐ SOFTLAB
"""

import os
import sys
import re
import csv
import json
import base64
import argparse
import subprocess
import html
import zipfile
from datetime import datetime, timedelta

def load_env(env_path):
    """Đọc cấu hình database từ file .env"""
    env_vars = {
        'DB_DATABASE': 'tmail',
        'DB_USERNAME': 'tmail',
        'DB_PASSWORD': 'tmail_secret_password',
    }
    if os.path.exists(env_path):
        with open(env_path, 'r', encoding='utf-8') as f:
            for line in f:
                line = line.strip()
                if not line or line.startswith('#') or '=' not in line:
                    continue
                k, v = line.split('=', 1)
                k = k.strip()
                v = v.strip().strip('"').strip("'")
                if k in env_vars:
                    env_vars[k] = v
    return env_vars

def clean_filename(s, max_len=40):
    """Tạo tên file an toàn từ tiêu đề hoặc email"""
    s = re.sub(r'[\\/*?:"<>|]', '_', s)
    s = re.sub(r'\s+', '_', s)
    s = s.strip('_')
    return s[:max_len] if len(s) > max_len else s

def strip_tags(html_text):
    """Chuyển HTML thành text tóm tắt"""
    if not html_text:
        return ""
    text = re.sub(r'<style[\s\S]*?</style>', '', html_text, flags=re.IGNORECASE)
    text = re.sub(r'<script[\s\S]*?</script>', '', text, flags=re.IGNORECASE)
    text = re.sub(r'<[^>]+>', ' ', text)
    text = html.unescape(text)
    text = re.sub(r'\s+', ' ', text).strip()
    return text

def query_messages(env_vars, args):
    """Truy vấn danh sách email từ MariaDB container"""
    db_name = env_vars['DB_DATABASE']
    db_user = env_vars['DB_USERNAME']
    db_pass = env_vars['DB_PASSWORD']

    where_clauses = []
    
    if args.to:
        safe_to = args.to.replace("'", "''")
        where_clauses.append(f"`to` LIKE '%{safe_to}%'")
    
    if args.sender:
        safe_from = args.sender.replace("'", "''")
        where_clauses.append(f"`from` LIKE '%{safe_from}%'")
        
    if args.subject:
        safe_subj = args.subject.replace("'", "''")
        where_clauses.append(f"`subject` LIKE '%{safe_subj}%'")
        
    if args.days:
        since_date = (datetime.now() - timedelta(days=args.days)).strftime('%Y-%m-%d 00:00:00')
        where_clauses.append(f"`created_at` >= '{since_date}'")
    elif args.since:
        safe_since = args.since.replace("'", "''")
        where_clauses.append(f"`created_at` >= '{safe_since}'")
        
    if args.until:
        safe_until = args.until.replace("'", "''")
        where_clauses.append(f"`created_at` <= '{safe_until}'")
        
    if args.search:
        safe_search = args.search.replace("'", "''")
        where_clauses.append(f"(`subject` LIKE '%{safe_search}%' OR `from` LIKE '%{safe_search}%' OR `to` LIKE '%{safe_search}%' OR `body` LIKE '%{safe_search}%')")

    where_sql = ("WHERE " + " AND ".join(where_clauses)) if where_clauses else ""
    limit_sql = f"LIMIT {int(args.limit)}" if args.limit else ""

    sql = f"""
    SELECT 
        id, 
        REPLACE(TO_BASE64(`from`), '\\n', ''), 
        REPLACE(TO_BASE64(`to`), '\\n', ''), 
        REPLACE(TO_BASE64(subject), '\\n', ''), 
        is_seen, 
        created_at, 
        REPLACE(TO_BASE64(COALESCE(attachments, '')), '\\n', ''), 
        REPLACE(TO_BASE64(COALESCE(body, '')), '\\n', '') 
    FROM messages 
    {where_sql}
    ORDER BY id DESC
    {limit_sql};
    """

    cmd = [
        'docker', 'compose', 'exec', '-T', 'db',
        'mariadb', '-u', db_user, f'-p{db_pass}', db_name,
        '--default-character-set=utf8mb4',
        '-N', '-B',
        '-e', sql
    ]

    try:
        res = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=True)
    except subprocess.CalledProcessError as e:
        sys.stderr.write(f"Lỗi truy vấn MariaDB: {e.stderr.decode('utf-8', errors='replace')}\n")
        sys.exit(1)

    raw_output = res.stdout.decode('utf-8', errors='replace')
    rows = []
    for line in raw_output.splitlines():
        if not line.strip():
            continue
        parts = line.split('\t')
        if len(parts) < 8:
            continue
        try:
            mid = int(parts[0])
            mfrom = base64.b64decode(parts[1]).decode('utf-8', errors='replace')
            mto = base64.b64decode(parts[2]).decode('utf-8', errors='replace')
            msubj = base64.b64decode(parts[3]).decode('utf-8', errors='replace')
            mseen = int(parts[4]) if parts[4].isdigit() else 0
            mdate = parts[5]
            matt = base64.b64decode(parts[6]).decode('utf-8', errors='replace')
            mbody = base64.b64decode(parts[7]).decode('utf-8', errors='replace')

            rows.append({
                'id': mid,
                'from': mfrom,
                'to': mto,
                'subject': msubj,
                'is_seen': mseen,
                'date': mdate,
                'attachments': matt if matt else None,
                'body': mbody,
                'snippet': strip_tags(mbody)[:250]
            })
        except Exception as ex:
            continue

    return rows

def export_json(rows, file_path):
    with open(file_path, 'w', encoding='utf-8') as f:
        json.dump(rows, f, ensure_ascii=False, indent=2)

def export_csv(rows, file_path):
    # Dùng utf-8-sig để Excel hiển thị đúng dấu tiếng Việt và Unicode
    with open(file_path, 'w', encoding='utf-8-sig', newline='') as f:
        writer = csv.writer(f)
        writer.writerow([
            'ID',
            'Thời gian nhận',
            'Người gửi (From)',
            'Người nhận (To)',
            'Tiêu đề (Subject)',
            'Đã xem',
            'Có đính kèm',
            'Xem trước nội dung'
        ])
        for r in rows:
            writer.writerow([
                r['id'],
                r['date'],
                r['from'],
                r['to'],
                r['subject'],
                'Có' if r['is_seen'] else 'Chưa',
                'Có' if r['attachments'] else 'Không',
                r['snippet']
            ])

def export_html(rows, html_dir, base_title="IMAIL Email Archive"):
    os.makedirs(html_dir, exist_ok=True)
    
    # Xuất từng file email
    index_items = []
    for r in rows:
        safe_to = clean_filename(r['to'], 20)
        safe_subj = clean_filename(r['subject'], 30) or 'no_subject'
        email_filename = f"{r['id']}_{safe_to}_{safe_subj}.html"
        email_path = os.path.join(html_dir, email_filename)

        email_html = f"""<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{html.escape(r['subject'])} - IMAIL</title>
    <style>
        body {{ font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; margin: 0; padding: 20px; background: #f3f4f6; color: #1f2937; }}
        .email-container {{ max-width: 900px; margin: 0 auto; background: #ffffff; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); overflow: hidden; }}
        .email-header {{ background: #0155b5; color: #ffffff; padding: 20px 24px; }}
        .email-header h1 {{ margin: 0 0 12px 0; font-size: 18px; line-height: 1.4; }}
        .meta-grid {{ display: grid; grid-template-columns: auto 1fr; gap: 6px 16px; font-size: 13px; opacity: 0.95; }}
        .meta-label {{ font-weight: bold; opacity: 0.85; }}
        .nav-back {{ margin-bottom: 12px; }}
        .nav-back a {{ color: #ffffff; text-decoration: underline; font-size: 13px; }}
        .email-body {{ padding: 24px; overflow-x: auto; font-size: 14px; line-height: 1.6; }}
        .attachments {{ background: #f9fafb; border-top: 1px solid #e5e7eb; padding: 12px 24px; font-size: 13px; }}
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <div class="nav-back"><a href="index.html">&larr; Quay lại danh sách email</a></div>
            <h1>{html.escape(r['subject'])}</h1>
            <div class="meta-grid">
                <div class="meta-label">Từ (From):</div>
                <div>{html.escape(r['from'])}</div>
                <div class="meta-label">Đến (To):</div>
                <div>{html.escape(r['to'])}</div>
                <div class="meta-label">Thời gian:</div>
                <div>{r['date']}</div>
                <div class="meta-label">Mã Email:</div>
                <div>#{r['id']}</div>
            </div>
        </div>
        <div class="email-body">
            {r['body']}
        </div>
        {f'<div class="attachments"><strong>Tệp đính kèm:</strong> {html.escape(r["attachments"])}</div>' if r["attachments"] else ''}
    </div>
</body>
</html>"""
        with open(email_path, 'w', encoding='utf-8') as f:
            f.write(email_html)

        index_items.append({
            'id': r['id'],
            'date': r['date'],
            'from': r['from'],
            'to': r['to'],
            'subject': r['subject'],
            'file': email_filename,
            'has_att': bool(r['attachments'])
        })

    # Tạo file index.html làm giao diện tra cứu offline
    rows_tr = []
    for item in index_items:
        att_badge = '<span style="color:#d97706; font-size:11px;">📎 Có</span>' if item['has_att'] else '-'
        rows_tr.append(f"""
        <tr>
            <td style="text-align:center;">{item['id']}</td>
            <td><code style="font-size:12px; color:#4b5563;">{item['date']}</code></td>
            <td>{html.escape(item['from'])}</td>
            <td><strong>{html.escape(item['to'])}</strong></td>
            <td>
                <a href="{html.escape(item['file'])}" style="color:#0155b5; text-decoration:none; font-weight:600;">
                    {html.escape(item['subject'] or '(Không có tiêu đề)')}
                </a>
            </td>
            <td style="text-align:center;">{att_badge}</td>
            <td style="text-align:center;">
                <a href="{html.escape(item['file'])}" style="background:#0155b5; color:#fff; padding:3px 8px; border-radius:4px; font-size:12px; text-decoration:none;">Xem thư &rarr;</a>
            </td>
        </tr>
        """)

    index_html = f"""<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{base_title}</title>
    <style>
        body {{ font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; margin: 0; padding: 24px; background: #f3f4f6; color: #111827; }}
        .header {{ max-width: 1200px; margin: 0 auto 20px auto; background: #0155b5; color: #fff; padding: 20px 24px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }}
        .header h1 {{ margin: 0 0 6px 0; font-size: 22px; }}
        .header p {{ margin: 0; font-size: 13px; opacity: 0.9; }}
        .table-wrap {{ max-width: 1200px; margin: 0 auto; background: #fff; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); overflow: hidden; }}
        .search-box {{ padding: 14px 20px; background: #f9fafb; border-bottom: 1px solid #e5e7eb; }}
        .search-input {{ width: 100%; box-sizing: border-box; padding: 8px 14px; font-size: 14px; border: 1px solid #d1d5db; border-radius: 6px; outline: none; }}
        table {{ width: 100%; border-collapse: collapse; font-size: 13px; }}
        th {{ background: #f3f4f6; color: #374151; font-weight: 600; text-align: left; padding: 10px 14px; border-bottom: 2px solid #e5e7eb; }}
        td {{ padding: 10px 14px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }}
        tr:hover {{ background-color: #f9fafb; }}
        .footer {{ max-width: 1200px; margin: 20px auto 0 auto; text-align: center; font-size: 12px; color: #6b7280; }}
    </style>
</head>
<body>
    <div class="header">
        <h1>{base_title}</h1>
        <p>Xuất lúc: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')} • Tổng số: <strong>{len(rows)} email</strong> • Hệ thống: imail.edu.vn (SoftLab)</p>
    </div>
    <div class="table-wrap">
        <div class="search-box">
            <input type="text" id="filterInput" class="search-input" placeholder="🔍 Gõ từ khóa để lọc nhanh người gửi, người nhận, tiêu đề..." onkeyup="filterTable()">
        </div>
        <table id="mailTable">
            <thead>
                <tr>
                    <th style="width: 50px; text-align:center;">ID</th>
                    <th style="width: 140px;">Thời gian</th>
                    <th style="width: 220px;">Người gửi</th>
                    <th style="width: 200px;">Hộp thư nhận</th>
                    <th>Tiêu đề</th>
                    <th style="width: 70px; text-align:center;">File</th>
                    <th style="width: 90px; text-align:center;">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                {''.join(rows_tr)}
            </tbody>
        </table>
    </div>
    <div class="footer">
        CÔNG TY TNHH GIẢI PHÁP SỐ SOFTLAB • https://github.com/SoftLabVN/tmail-dockerized
    </div>
    <script>
        function filterTable() {{
            const input = document.getElementById('filterInput').value.toLowerCase();
            const rows = document.getElementById('mailTable').getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            for (let i = 0; i < rows.length; i++) {{
                const text = rows[i].innerText.toLowerCase();
                rows[i].style.display = text.includes(input) ? '' : 'none';
            }}
        }}
    </script>
</body>
</html>"""
    with open(os.path.join(html_dir, 'index.html'), 'w', encoding='utf-8') as f:
        f.write(index_html)

def export_summary(rows, file_path):
    to_counter = {}
    from_counter = {}
    for r in rows:
        to_counter[r['to']] = to_counter.get(r['to'], 0) + 1
        from_counter[r['from']] = from_counter.get(r['from'], 0) + 1

    top_to = sorted(to_counter.items(), key=lambda x: x[1], reverse=True)[:10]
    top_from = sorted(from_counter.items(), key=lambda x: x[1], reverse=True)[:10]

    with open(file_path, 'w', encoding='utf-8') as f:
        f.write("=" * 60 + "\n")
        f.write("      BÁO CÁO THỐNG KÊ LỊCH SỬ EMAIL TMAIL (imail.edu.vn)\n")
        f.write("=" * 60 + "\n\n")
        f.write(f"Thời gian xuất báo cáo : {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}\n")
        f.write(f"Tổng số email xuất    : {len(rows)}\n")
        if rows:
            f.write(f"Email mới nhất        : #{rows[0]['id']} ({rows[0]['date']})\n")
            f.write(f"Email cũ nhất         : #{rows[-1]['id']} ({rows[-1]['date']})\n")
        f.write("\n" + "-" * 60 + "\n")
        f.write("TOP 10 HỘP THƯ NHẬN NHIỀU EMAIL NHẤT:\n")
        f.write("-" * 60 + "\n")
        for email, count in top_to:
            f.write(f" - {email:<35} : {count:>5} email\n")

        f.write("\n" + "-" * 60 + "\n")
        f.write("TOP 10 ĐỊA CHỈ GỬI ĐẾN NHIỀU NHẤT:\n")
        f.write("-" * 60 + "\n")
        for email, count in top_from:
            f.write(f" - {email:<45} : {count:>5} email\n")
        f.write("\n" + "=" * 60 + "\n")
        f.write("Đơn vị vận hành: CÔNG TY TNHH GIẢI PHÁP SỐ SOFTLAB\n")
        f.write("=" * 60 + "\n")

def make_zip(source_dir, output_zip_path):
    with zipfile.ZipFile(output_zip_path, 'w', zipfile.ZIP_DEFLATED) as zipf:
        for root, dirs, files in os.walk(source_dir):
            for file in files:
                file_path = os.path.join(root, file)
                rel_path = os.path.relpath(file_path, os.path.dirname(source_dir))
                zipf.write(file_path, rel_path)

def main():
    parser = argparse.ArgumentParser(
        description="Xuất tất cả lịch sử thư điện tử đã nhận trong TMail ra file (JSON, CSV, HTML, ZIP)"
    )
    parser.add_argument(
        '--format', '-f',
        choices=['all', 'json', 'csv', 'html'],
        default='all',
        help="Định dạng xuất (all: xuất cả json, csv, html và tóm tắt; json; csv; html)"
    )
    parser.add_argument(
        '--output', '-o',
        help="Thư mục xuất file (mặc định: exports/export_YYYYMMDD_HHMMSS)"
    )
    parser.add_argument(
        '--to',
        help="Lọc theo địa chỉ người nhận (To)"
    )
    parser.add_argument(
        '--from',
        dest='sender',
        help="Lọc theo địa chỉ người gửi (From)"
    )
    parser.add_argument(
        '--subject',
        help="Lọc theo tiêu đề thư"
    )
    parser.add_argument(
        '--search',
        help="Tìm kiếm từ khóa trong người gửi, người nhận, tiêu đề hoặc nội dung"
    )
    parser.add_argument(
        '--days',
        type=int,
        help="Chỉ xuất email trong N ngày gần nhất (vd: --days 7)"
    )
    parser.add_argument(
        '--since',
        help="Chỉ xuất email từ ngày (YYYY-MM-DD hoặc YYYY-MM-DD HH:MM:SS)"
    )
    parser.add_argument(
        '--until',
        help="Chỉ xuất email đến ngày (YYYY-MM-DD hoặc YYYY-MM-DD HH:MM:SS)"
    )
    parser.add_argument(
        '--limit',
        type=int,
        help="Giới hạn số lượng email cần xuất (vd: --limit 500)"
    )
    parser.add_argument(
        '--zip',
        action='store_true',
        help="Nén toàn bộ kết quả xuất thành file .zip"
    )
    parser.add_argument(
        '--env',
        default='.env',
        help="Đường dẫn tới file .env (mặc định: .env)"
    )

    args = parser.parse_args()

    project_root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    os.chdir(project_root)

    env_path = os.path.join(project_root, args.env)
    env_vars = load_env(env_path)

    timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
    if args.output:
        out_dir = os.path.abspath(args.output)
    else:
        out_dir = os.path.join(project_root, 'exports', f'export_{timestamp}')

    os.makedirs(out_dir, exist_ok=True)

    print("=" * 65)
    print(" 🚀 TIẾN TRÌNH XUẤT LỊCH SỬ EMAIL TMAIL (imail.edu.vn)")
    print("=" * 65)
    print(f"[*] Đang kết nối database và truy vấn dữ liệu...")

    rows = query_messages(env_vars, args)
    total_count = len(rows)

    if total_count == 0:
        print("[!] Không tìm thấy email nào phù hợp với bộ lọc!")
        sys.exit(0)

    print(f"[+] Tìm thấy {total_count} email.")
    print(f"[*] Đang xuất dữ liệu ra thư mục: {out_dir}")

    fmt = args.format.lower()
    exported_files = []

    if fmt in ('all', 'json'):
        json_file = os.path.join(out_dir, 'emails.json')
        export_json(rows, json_file)
        exported_files.append(('JSON', json_file, os.path.getsize(json_file)))

    if fmt in ('all', 'csv'):
        csv_file = os.path.join(out_dir, 'emails.csv')
        export_csv(rows, csv_file)
        exported_files.append(('CSV (Excel)', csv_file, os.path.getsize(csv_file)))

    if fmt in ('all', 'html'):
        html_dir = os.path.join(out_dir, 'html')
        export_html(rows, html_dir)
        index_file = os.path.join(html_dir, 'index.html')
        exported_files.append(('HTML Archive (Web)', index_file, sum(os.path.getsize(os.path.join(html_dir, f)) for f in os.listdir(html_dir))))

    if fmt == 'all':
        summary_file = os.path.join(out_dir, 'summary.txt')
        export_summary(rows, summary_file)
        exported_files.append(('Summary Báo Cáo', summary_file, os.path.getsize(summary_file)))

    zip_file = None
    if args.zip or fmt == 'all':
        zip_path = os.path.join(project_root, 'exports', f'emails_{timestamp}.zip')
        make_zip(out_dir, zip_path)
        zip_file = zip_path

    print("\n" + "=" * 65)
    print(" 🎉 HOÀN TẤT XUẤT DỮ LIỆU EMAIL!")
    print("=" * 65)
    print(f"Tổng số email: {total_count}")
    print(f"Thư mục lưu  : {out_dir}\n")
    print("Các tệp đã tạo:")
    for ftype, fpath, fsize in exported_files:
        size_kb = fsize / 1024
        size_str = f"{size_kb:.1f} KB" if size_kb < 1024 else f"{(size_kb/1024):.2f} MB"
        print(f" - [{ftype:<16}] {fpath} ({size_str})")

    if zip_file:
        zip_size_kb = os.path.getsize(zip_file) / 1024
        zip_size_str = f"{zip_size_kb:.1f} KB" if zip_size_kb < 1024 else f"{(zip_size_kb/1024):.2f} MB"
        print(f"\n📦 Tệp nén trọn gói : {zip_file} ({zip_size_str})")

    print("\n" + "-" * 65)
    print("💡 Gợi ý sử dụng:")
    print(f"  • Mở file CSV bằng Excel: {os.path.join(out_dir, 'emails.csv') if fmt in ('all', 'csv') else ''}")
    print(f"  • Xem trực tiếp trong trình duyệt: {os.path.join(out_dir, 'html', 'index.html') if fmt in ('all', 'html') else ''}")
    print("-" * 65)

if __name__ == '__main__':
    main()
