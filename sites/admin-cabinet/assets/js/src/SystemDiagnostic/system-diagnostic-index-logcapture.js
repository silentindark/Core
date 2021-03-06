/*
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Nikolay Beketov, 8 2020
 */
/* global sessionStorage, PbxApi */

const archivePackingCheckWorker = {
	timeOut: 3000,
	timeOutHandle: '',
	errorCounts: 0,
	filename: '',
	initialize(filename) {
		archivePackingCheckWorker.filename = filename;
		archivePackingCheckWorker.restartWorker(filename);
	},
	restartWorker() {
		window.clearTimeout(archivePackingCheckWorker.timeoutHandle);
		archivePackingCheckWorker.worker();
	},
	worker() {
		PbxApi.SyslogDownloadLogsArchive(archivePackingCheckWorker.filename, archivePackingCheckWorker.cbAfterResponse);
		archivePackingCheckWorker.timeoutHandle = window.setTimeout(
			archivePackingCheckWorker.worker,
			archivePackingCheckWorker.timeOut,
		);
	},
	cbAfterResponse(response) {
		if (archivePackingCheckWorker.errorCounts > 50) {
			UserMessage.showMultiString(globalTranslate.sd_DownloadPcapFileError);
			systemDiagnosticCapture.$stopBtn
				.removeClass('disabled loading')
				.addClass('disabled');
			systemDiagnosticCapture.$startBtn.removeClass('disabled loading');
			window.clearTimeout(archivePackingCheckWorker.timeoutHandle);
		}
		if (response === undefined || Object.keys(response).length === 0) {
			archivePackingCheckWorker.errorCounts += 1;
			return;
		}
		if (response.status === 'READY') {
			systemDiagnosticCapture.$stopBtn
				.removeClass('disabled loading')
				.addClass('disabled');
			systemDiagnosticCapture.$startBtn.removeClass('disabled loading');
			window.location = response.filename;
			window.clearTimeout(archivePackingCheckWorker.timeoutHandle);
			systemDiagnosticCapture.$dimmer.removeClass('active');
		} else if (response.status !== undefined) {
			archivePackingCheckWorker.errorCounts = 0;
		} else {
			archivePackingCheckWorker.errorCounts += 1;
		}
	},
};

const systemDiagnosticCapture = {
	$startBtn: $('#start-capture-button'),
	$stopBtn: $('#stop-capture-button'),
	$showBtn: $('#show-last-log'),
	$dimmer:  $('#capture-log-dimmer'),
	initialize() {
		const segmentHeight = window.innerHeight-300;
		$(window).load(function() {
			systemDiagnosticCapture.$dimmer.closest('div').css('min-height', `${segmentHeight}px`);
		});
		if (sessionStorage.getItem('LogsCaptureStatus') === 'started') {
			systemDiagnosticCapture.$startBtn.addClass('disabled loading');
			systemDiagnosticCapture.$stopBtn.removeClass('disabled');
		} else {
			systemDiagnosticCapture.$startBtn.removeClass('disabled loading');
			systemDiagnosticCapture.$stopBtn.addClass('disabled');
		}
		systemDiagnosticCapture.$startBtn.on('click', (e) => {
			e.preventDefault();
			systemDiagnosticCapture.$startBtn.addClass('disabled loading');
			systemDiagnosticCapture.$stopBtn.removeClass('disabled');
			PbxApi.SyslogStartLogsCapture(systemDiagnosticCapture.cbAfterStartCapture);
		});
		systemDiagnosticCapture.$stopBtn.on('click', (e) => {
			e.preventDefault();
			systemDiagnosticCapture.$startBtn.removeClass('loading');
			systemDiagnosticCapture.$stopBtn.addClass('loading');
			systemDiagnosticCapture.$dimmer.addClass('active');
			PbxApi.SyslogStopLogsCapture(systemDiagnosticCapture.cbAfterStopCapture);

		});
	},
	/**
	 *  Callback after push start logs collect button
	 * @param response
	 */
	cbAfterStartCapture(response){
		if (response!==false) {
			sessionStorage.setItem('LogsCaptureStatus', 'started');
			setTimeout(() => {
				sessionStorage.setItem('LogsCaptureStatus', 'stopped');
			}, 300000);
		}
	},
	/**
	 * Callback after push stop logs collect button
	 * @param response
	 */
	cbAfterStopCapture(response){
		if (response!==false){
			archivePackingCheckWorker.initialize(response.filename);
		}
	}
};

$(document).ready(() => {
	systemDiagnosticCapture.initialize();
});

