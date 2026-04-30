<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Layout Debug Test</title>
    <style>
        /* Reset everything */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            background: #0d0d0f;
            color: white;
            font-family: Arial, sans-serif;
        }
        
        .studio-shell {
            display: flex;
            height: 100vh;
            background: #0d0d0f;
            border: 2px solid red; /* Debug border */
        }
        
        .studio-sidebar {
            width: 280px;
            background: rgba(255,255,255,0.02);
            border-right: 1px solid rgba(255,255,255,0.08);
            flex-shrink: 0;
            border: 2px solid green; /* Debug border */
        }
        
        .studio-main-wrap {
            flex: 1;
            display: flex;
            flex-direction: column;
            border: 2px solid blue; /* Debug border */
        }
        
        .studio-header {
            background: rgba(255,255,255,0.04);
            border-bottom: 1px solid rgba(255,255,255,0.08);
            padding: 15px 20px;
            flex-shrink: 0;
            border: 2px solid yellow; /* Debug border */
        }
        
        .studio-main {
            flex: 1;
            overflow-y: auto;
            padding: 30px;
            border: 2px solid orange; /* Debug border */
        }
    </style>
</head>
<body>
    <div class="studio-shell">
        <div class="studio-sidebar">
            <h3>Sidebar</h3>
            <p>Fixed width: 280px</p>
        </div>
        <div class="studio-main-wrap">
            <div class="studio-header">
                <h3>Header</h3>
                <p>Fixed height, no shrink</p>
            </div>
            <div class="studio-main">
                <h3>Main Content</h3>
                <p>This should fill remaining space and scroll if needed.</p>
                <div style="height: 2000px; background: rgba(255,255,255,0.1); margin: 20px 0; padding: 20px;">
                    <p>Long content to test scrolling...</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
