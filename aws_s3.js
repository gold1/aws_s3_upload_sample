function AwsS3(server_url, file) {
    this.server_url = server_url;
    this.file = file;
    /**
     * .key
     * .url
     */
    this.result = [];
}
    AwsS3.prototype.start = function() {
        var self = this;
        if (typeof this.file === 'undefined' ||
            typeof this.file.name === 'undefined' ||
            this.file.name.length == 0 ||
            this.file.size == 0) {
            this.onServerError("ファイルを選択してください。");
            return;
        }
        $.ajax({
            type:'POST',
            url:this.server_url,
            data: {
                name: this.file.name
            }
        })
        .done(function(data) {
            self.sendToS3(data);
        }).fail(function(data) {
            self.onServerError("通信エラーにより、アップロード用URLの取得に失敗しました。");
        });
    };
    AwsS3.prototype.sendToS3 = function(response) {
        var self = this;
        if (typeof response.url === 'undefined') {
            this.onServerError("通信エラーにより、アップロード用URLの取得に失敗しました。");
            return;
        }
        $.ajax({
            type:'PUT',
            url: response.url,
            contentType: "binary/octet-stream",
            processData: false,
            data: this.file
        })
        .done(function(data, textStatus, jqXHR) {
            if (textStatus != 'success') {
                self.onServerError('通信エラーにより、アップロードが途中で終了しました。');
                return;
            }
            self.result = $.extend(true, {}, response);
            self.onUploadCompleted(self.result);
        }).fail(function() {
            self.onServerError('通信エラーにより、アップロードが途中で失敗しました。');
        });
    };
    AwsS3.prototype.onServerError = function(message) {
    };
    AwsS3.prototype.onUploadCompleted = function(serverData) {
    };
