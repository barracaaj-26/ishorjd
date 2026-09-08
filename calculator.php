<?php
session_start();

$stanzas = [
    "“Bakit nga ba mahal kita”",
    "“Kahit 'di pinapansin ang damdamin ko?”",
    "“'Di mo man ako mahal, heto pa rin ako”",
    "“Nagmamahal nang tapat sa 'yo”"
];

if (!isset($_SESSION['stanza_index'])) {
    $_SESSION['stanza_index'] = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'clear') {
        $_SESSION['stanza_index'] = 0;
        echo json_encode(['status' => 'cleared']);
        exit;
    }

    if ($action === 'next_lyric') {
        $idx = $_SESSION['stanza_index'] % count($stanzas);
        $currentLyric = $stanzas[$idx];
        $_SESSION['stanza_index']++;
        echo json_encode(['lyric' => $currentLyric]);
        exit;
    }

    if ($action === 'calculate') {
        $expr = trim($input['expression'] ?? '');
        
        $sanitizedExpr = str_replace(['×', '÷', '−', '%'], ['*', '/', '-', '/100'], $expr);
        $sanitizedExpr = preg_replace('/[\+\-\*\/]+$/', '', $sanitizedExpr);
        
        if (empty($sanitizedExpr)) {
            $sanitizedExpr = "0";
        }
        
        $result = "0";
        
        try {
            $evalResult = @eval("return ($sanitizedExpr);");
            if ($evalResult !== false && $evalResult !== null) {
                $result = round($evalResult, 4);
            }
        } catch (Throwable $e) {
            $result = "0";
        }

        $_SESSION['stanza_index'] = 1;
        $currentLyric = $stanzas[0];

        echo json_encode([
            'result' => $result,
            'lyric' => $currentLyric
        ]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calculator Lyrics</title>
    <style>
        * {
            box-sizing: border-box;
            user-select: none;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #121212;
            color: #ffffff;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .phone-frame {
            background-color: #000000;
            width: 360px;
            padding: 25px 20px 30px;
            border-radius: 40px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.8), 0 0 0 1px #333333;
            display: flex;
            flex-direction: column;
        }
        
        .display-container {
            min-height: 200px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            align-items: flex-end;
            padding: 10px 10px 15px;
            word-wrap: break-word;
            word-break: break-all;
        }
        .expression-display {
            color: #8e8e93;
            font-size: 20px;
            margin-bottom: 4px;
            min-height: 24px;
        }
        .main-display {
            color: #ffffff;
            font-size: 56px;
            font-weight: 300;
            line-height: 1.1;
            transition: font-size 0.1s ease;
        }

        .inline-lyric {
            color: #ffd60a;
            font-size: 13px;
            font-style: italic;
            text-align: right;
            margin-top: 10px;
            min-height: 40px;
            white-space: pre-line;
            line-height: 1.35;
        }

        .keypad {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-top: 10px;
        }
        .btn {
            aspect-ratio: 1;
            border-radius: 50%;
            border: none;
            outline: none;
            font-size: 26px;
            font-weight: 400;
            cursor: pointer;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: filter 0.1s ease, transform 0.05s ease;
        }
        .btn:active {
            filter: brightness(1.4);
            transform: scale(0.96);
        }

        .btn-dark {
            background-color: #2c2c2e;
            color: #ffffff;
        }
        .btn-orange {
            background-color: #ff9f0a;
            color: #ffffff;
            font-size: 32px;
        }
    </style>
</head>
<body>

<div class="phone-frame">
    <div class="display-container">
        <div class="expression-display" id="expression"></div>
        <div class="main-display" id="display">0</div>
        <div class="inline-lyric" id="lyricText"></div>
    </div>

    <div class="keypad">
        <button class="btn btn-dark" onclick="backspace()">⌫</button>
        <button class="btn btn-dark" onclick="clearAll()">AC</button>
        <button class="btn btn-dark" onclick="inputOperator('%')">%</button>
        <button class="btn btn-orange" onclick="inputOperator('÷')">÷</button>

        <button class="btn btn-dark" onclick="inputNum('7')">7</button>
        <button class="btn btn-dark" onclick="inputNum('8')">8</button>
        <button class="btn btn-dark" onclick="inputNum('9')">9</button>
        <button class="btn btn-orange" onclick="inputOperator('×')">×</button>

        <button class="btn btn-dark" onclick="inputNum('4')">4</button>
        <button class="btn btn-dark" onclick="inputNum('5')">5</button>
        <button class="btn btn-dark" onclick="inputNum('6')">6</button>
        <button class="btn btn-orange" onclick="inputOperator('−')">−</button>

        <button class="btn btn-dark" onclick="inputNum('1')">1</button>
        <button class="btn btn-dark" onclick="inputNum('2')">2</button>
        <button class="btn btn-dark" onclick="inputNum('3')">3</button>
        <button class="btn btn-orange" onclick="inputOperator('+')">+</button>

        <button class="btn btn-dark" onclick="toggleSign()">+/−</button>
        <button class="btn btn-dark" onclick="inputNum('0')">0</button>
        <button class="btn btn-dark" onclick="inputDot()">.</button>
        <button class="btn btn-orange" onclick="calculate()">=</button>
    </div>
</div>

<script>
    let currentInput = "0";
    let previousExpression = "";
    let shouldResetDisplay = false;
    let fallbackIndex = 0;

    const jsStanzas = [
        "“Bakit nga ba mahal kita”",
        "“Kahit 'di pinapansin ang damdamin ko?”",
        "“'Di mo man ako mahal, heto pa rin ako”",
        "“Nagmamahal nang tapat sa 'yo”"
    ];

    const displayEl = document.getElementById("display");
    const expressionEl = document.getElementById("expression");
    const lyricTextEl = document.getElementById("lyricText");

    function updateDisplay() {
        displayEl.innerText = currentInput;
        expressionEl.innerText = previousExpression;
        
        if (currentInput.length > 8) {
            displayEl.style.fontSize = "36px";
        } else if (currentInput.length > 5) {
            displayEl.style.fontSize = "44px";
        } else {
            displayEl.style.fontSize = "56px";
        }
    }

    function inputNum(num) {
        if (currentInput === "0" || shouldResetDisplay) {
            currentInput = num;
            shouldResetDisplay = false;
        } else {
            currentInput += num;
        }
        updateDisplay();
    }

    function inputDot() {
        if (shouldResetDisplay) {
            currentInput = "0.";
            shouldResetDisplay = false;
        } else if (!currentInput.includes(".")) {
            currentInput += ".";
        }
        updateDisplay();
    }

    function toggleSign() {
        if (currentInput !== "0") {
            currentInput = currentInput.startsWith("-") ? currentInput.slice(1) : "-" + currentInput;
            updateDisplay();
        }
    }

    function backspace() {
        if (shouldResetDisplay) return;
        currentInput = currentInput.length > 1 ? currentInput.slice(0, -1) : "0";
        updateDisplay();
    }

    function clearAll() {
        currentInput = "0";
        previousExpression = "";
        shouldResetDisplay = false;
        fallbackIndex = 0;
        lyricTextEl.innerText = "";
        updateDisplay();

        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'clear' })
        }).catch(() => {});
    }

    function inputOperator(op) {
        if (shouldResetDisplay) shouldResetDisplay = false;
        previousExpression += " " + currentInput + " " + op;
        currentInput = "0";
        updateDisplay();
    }

    function fetchNextLyric() {
        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'next_lyric' })
        })
        .then(res => res.json())
        .then(data => {
            lyricTextEl.innerText = data.lyric;
        })
        .catch(() => {
            lyricTextEl.innerText = jsStanzas[fallbackIndex % jsStanzas.length];
            fallbackIndex++;
        });
    }

    function calculate() {
        if (shouldResetDisplay) {
            fetchNextLyric();
            return;
        }

        let fullExpr = previousExpression ? (previousExpression + " " + currentInput) : currentInput;

        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'calculate',
                expression: fullExpr
            })
        })
        .then(response => {
            if (!response.ok) throw new Error("HTTP error");
            return response.json();
        })
        .then(data => {
            if (previousExpression) {
                previousExpression = fullExpr;
            }
            currentInput = String(data.result);
            shouldResetDisplay = true;

            updateDisplay();
            lyricTextEl.innerText = data.lyric;
        })
        .catch(err => {
            let sanitizedExpr = fullExpr.replace(/×/g, "*").replace(/÷/g, "/").replace(/−/g, "-").replace(/%/g, "/100");
            sanitizedExpr = sanitizedExpr.replace(/[\+\-\*\/]+$/, '');
            if (!sanitizedExpr) sanitizedExpr = "0";

            try {
                let res = eval(sanitizedExpr);
                currentInput = String(Math.round(res * 10000) / 10000);
            } catch (e) {
                currentInput = "0";
            }

            shouldResetDisplay = true;
            updateDisplay();

            fallbackIndex = 0;
            lyricTextEl.innerText = jsStanzas[fallbackIndex];
            fallbackIndex = 1;
        });
    }
</script>

</body>
</html>