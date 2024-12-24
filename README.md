# PHP Simple Web Server
PHPスクリプトだけを使用してマルチプロセスのWebサーバーを構築するという誰もが一度は考えつく...。  
だがやる。裸一貫。全てを脱ぎ捨てて丸腰でPHPと向き合う戦いを執り行う。  
とは言っても苦手なことを頑張るのはちょっと違うので、**Nginx**をフロントに配置して負荷分散及びSSL対応のプロキシとして利用をする。

---

## Nginxの役割
- 複数のPHPプロセスに対するリクエストの分散
- SSL終端化
- クライアントからのリクエストをPHPにプロキシとして転送

## PHPの役割
- HTTPリクエストの処理とレスポンス生成
- 静的ファイル・動的ルーティングの処理
- 強く生きる

PHPが主要要素となったシンプルなWebサーバーと言えるはずだ。

---

## 起動
```shell
git clone https://github.com/nzsys/simple-php-server
cd simple-php-server
docker-compose up -d
```

## 確認
URL: [http://localhost:8080](http://localhost:8080)

---

## どこまで頑張れるか
1. ワーカープロセスを増やす
server.php
```php
$workerCount = 4;
```

2. 分散を増やす
docker-compose.ymlのphpサーバーを増やし、nginx.confのupstreamを追加する。

以上です()

### お気持ち
PHPってWebサーバーもあるんだ へー  
は？ビルドインサーバーってシングルスレッドなの？使えねー！      
は？動かねーmod_phpってなによ！？  
あ？fastcgi？んだよそれはよー！え何？php-fpmを使う？unit？なんでPHP使ってるの？    

そういったイジられかたもう飽きわ。

### 将来性（ない）
systemdでPHPプロセスを管理して必要な数を乗じ起動させることもできる。  
DockerでPHPサーバーをコンテナ化してエデンを目指せ。

手作りはイイゾ
