<?php
try {
    // Utwórz nową instancję Redis
    $redis = new redis();

    // Połącz się z Redis (127.0.0.1:6379)
    $redis->connect('127.0.0.1', 6379);

    // Wykonaj komendę PING
    $response = $redis->ping();

    // Wyświetl odpowiedź
    echo "Połączenie z Redis działa! Odpowiedź: " . $response . PHP_EOL;

    // Przetestuj zapis i odczyt danych
    $redis->set("klucz_testowy", "Witaj Redis!");
    echo "Odczyt z Redis: " . $redis->get("klucz_testowy") . PHP_EOL;

} catch (Exception $e) {
    // Wyświetl błędy w przypadku problemów z połączeniem
    echo "Błąd: " . $e->getMessage() . PHP_EOL;
}
?>
