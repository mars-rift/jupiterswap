<?php
$displayMode = isset($_GET['mode']) ? htmlspecialchars($_GET['mode']) : 'integrated';
$integratedTargetId = 'swap-root';
$logoUri = isset($_GET['logo']) ? htmlspecialchars($_GET['logo']) : '';
$primaryColor = isset($_GET['primary']) ? htmlspecialchars($_GET['primary']) : '#D4AF37';
$interactiveColor = isset($_GET['interactive']) ? htmlspecialchars($_GET['interactive']) : '#228B22';
$defaultProps = json_encode(["displayMode" => $displayMode, "integratedTargetId" => $integratedTargetId]);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Jupiter Swap (PHP) — <?php echo $displayMode; ?></title>

    <script src="./plugin-v1.js" data-preload></script>
    <link rel="stylesheet" href="./main-0.1.17-Tailwind.css" />
    <link rel="stylesheet" href="./main-0.1.17-Jupiter.css" />
    <link rel="stylesheet" href="./scoped-preflight-v4.css" />
    <link rel="stylesheet" href="./swap.css" />

    <style>body { background: black; color: white; }</style>
  </head>
  <body>
    <div id="swap-root" style="height: 700px; width: 360px; margin: 48px auto;"></div>

    <script>
      (function () {
        // Optional: apply some server-supplied styles
        const primary = '<?php echo $primaryColor; ?>';
        const interactive = '<?php echo $interactiveColor; ?>';
        try {
          const root = document.documentElement;
          // Convert hex to rgb
          function hexToRgb(hex) {
            const m = hex.replace('#','');
            const bigint = parseInt(m, 16);
            const r = (bigint >> 16) & 255;
            const g = (bigint >> 8) & 255;
            const b = bigint & 255;
            return `${r}, ${g}, ${b}`;
          }
          root.style.setProperty('--jupiter-plugin-primary', hexToRgb(primary));
          root.style.setProperty('--jupiter-plugin-interactive', hexToRgb(interactive));
        } catch (e) {
          console.warn(e);
        }

        function waitForInit() {
          if (!window.Jupiter.init) {
            setTimeout(waitForInit, 50);
            return;
          }
          window.Jupiter.init(<?php echo $defaultProps; ?>);
        }

        if (document.readyState === 'complete') waitForInit();
        else document.addEventListener('readystatechange', () => { if (document.readyState === 'complete') waitForInit(); });
      })();
    </script>
  </body>
</html>
