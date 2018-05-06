function AwsS3(server_url, file, options) {
    var options = typeof options !== 'undefined' ?  options : {};
    if (typeof options.is_multi === 'undefined') {
        options.is_multi = false;
    }
    this.TASK_CREATE = 1;
    this.TASK_UPLOAD = 2;
    this.TASK_COMPLETE = 3;
    this.PART_SIZE = 10 * 1024 * 1024; //minimum part size defined by aws s3
    this.server_url = server_url;
    this.file = file;
    this.fileInfo = {
        name: '',
        type: '',
        size: '',
        lastModified: ''
    };
    this.options = options;
    this.sendBackData = {
        uploadId: '',
        key: '',
        urls: [],
    };
    this.uploadXHR = null;
    this.uploadedSize = 0;
    this.uploadingSize = 0;
    this.partNum = 0;
    this.uploadCount = 0;
    this.taskNo = 0;
    this.progress = [];
    this.blobs = [];
    this.blob = null;
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
        this.fileInfo = {
            name: this.file.name,
            type: this.file.type,
            size: this.file.size,
            lastModified: this.file.lastModified
        };
        if (this.options.is_multi) {
            this.execTask(this.TASK_CREATE);
        } else {
            this.createSingleUpload();
        }
    };

    AwsS3.prototype.execTask = function(taskNo) {
        this.taskNo = taskNo;
        switch (taskNo) {
            case this.TASK_CREATE:
                this.createMultipartUpload();
                break;
            case this.TASK_UPLOAD:
                this.sendToS3Multi();
                break;
            case this.TASK_COMPLETE:
                this.completeMultipartUpload();
                break;
            default:
                this.onServerError("通信エラーが発生しました。");
                break;
        }
    };

    AwsS3.prototype.createMultipartUpload = function() {
        var self = this;
        var start, end = 0;
        this.blobs = [];
        var lengthes = [];
        while (end < this.file.size) {
            start = this.PART_SIZE * this.partNum;
            end = Math.min(start + this.PART_SIZE, this.file.size);
            lengthes[this.partNum++] = end - start;
            this.blobs.push(this.file.slice(start, end));
        }
        $.get(self.server_url, {
            command: 'CreateMultipartUpload',
            fileInfo: self.fileInfo,
            lengthes: lengthes,
            options: self.options
        }).done(function(data) {
            self.sendBackData = data;
            self.execTask(self.TASK_UPLOAD);
        }).fail(function(jqXHR, textStatus, errorThrown) {
            self.onServerError("通信エラーが発生しました。");
        });
    };

    AwsS3.prototype.sendToS3Multi = function() {
        var self = this;
        $.ajax({
            type:'PUT',
            url: self.sendBackData.urls[self.uploadCount],
            contentType: "binary/octet-stream",
            processData: false,
            data: self.blobs[self.uploadCount]
        })
        .done(function(data, textStatus, jqXHR) {
            self.uploadCount++;
            if (self.uploadCount < self.partNum) {
                self.execTask(self.TASK_UPLOAD);
                return;
            }
            self.execTask(self.TASK_COMPLETE);
        }).fail(function() {
            self.onServerError('通信エラーにより、アップロードが途中で失敗しました。');
        });
    };

    AwsS3.prototype.completeMultipartUpload = function() {
        var self = this;
        $.get(self.server_url, {
            command: 'CompleteMultipartUpload',
            sendBackData: self.sendBackData
        }).done(function(data) {
            self.onUploadCompleted(data);
        }).fail(function(jqXHR, textStatus, errorThrown) {
            self.onServerError("通信エラーが発生しました。");
        });
    };

    AwsS3.prototype.createSingleUpload = function() {
        var self = this;
        $.ajax({
            type:'POST',
            url:self.server_url,
            data: {
                name: self.file.name
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
            this.onServerError('sendToS3 res error');
            return;
        }
        $.ajax({
            type:'PUT',
            url: response.url,
            contentType: "binary/octet-stream",
            processData: false,
            data: self.file
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
