"use strict";

/*
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 8 2020
 */

/* global globalRootUrl,globalTranslate, Extensions, Form */
var incomingRouteModify = {
  $formObj: $('#incoming-route-form'),
  $providerDropDown: $('#provider'),
  $forwardingSelectDropdown: $('#incoming-route-form .forwarding-select'),
  validateRules: {
    extension: {
      identifier: 'extension',
      rules: [{
        type: 'empty',
        prompt: globalTranslate.ir_ValidateForwardingToBeFilled
      }]
    },
    timeout: {
      identifier: 'timeout',
      rules: [{
        type: 'integer[3..300]',
        prompt: globalTranslate.ir_ValidateTimeoutOutOfRange
      }]
    }
  },
  initialize: function () {
    function initialize() {
      incomingRouteModify.$providerDropDown.dropdown();
      incomingRouteModify.initializeForm();
      incomingRouteModify.$forwardingSelectDropdown.dropdown(Extensions.getDropdownSettingsWithoutEmpty());
    }

    return initialize;
  }(),
  cbBeforeSendForm: function () {
    function cbBeforeSendForm(settings) {
      var result = settings;
      result.data = incomingRouteModify.$formObj.form('get values');
      return result;
    }

    return cbBeforeSendForm;
  }(),
  cbAfterSendForm: function () {
    function cbAfterSendForm() {}

    return cbAfterSendForm;
  }(),
  initializeForm: function () {
    function initializeForm() {
      Form.$formObj = incomingRouteModify.$formObj;
      Form.url = "".concat(globalRootUrl, "incoming-routes/save");
      Form.validateRules = incomingRouteModify.validateRules;
      Form.cbBeforeSendForm = incomingRouteModify.cbBeforeSendForm;
      Form.cbAfterSendForm = incomingRouteModify.cbAfterSendForm;
      Form.initialize();
    }

    return initializeForm;
  }()
};
$(document).ready(function () {
  incomingRouteModify.initialize();
});
//# sourceMappingURL=data:application/json;charset=utf-8;base64,eyJ2ZXJzaW9uIjozLCJzb3VyY2VzIjpbIi4uLy4uL3NyYy9JbmNvbWluZ1JvdXRlcy9pbmNvbWluZy1yb3V0ZS1tb2RpZnkuanMiXSwibmFtZXMiOlsiaW5jb21pbmdSb3V0ZU1vZGlmeSIsIiRmb3JtT2JqIiwiJCIsIiRwcm92aWRlckRyb3BEb3duIiwiJGZvcndhcmRpbmdTZWxlY3REcm9wZG93biIsInZhbGlkYXRlUnVsZXMiLCJleHRlbnNpb24iLCJpZGVudGlmaWVyIiwicnVsZXMiLCJ0eXBlIiwicHJvbXB0IiwiZ2xvYmFsVHJhbnNsYXRlIiwiaXJfVmFsaWRhdGVGb3J3YXJkaW5nVG9CZUZpbGxlZCIsInRpbWVvdXQiLCJpcl9WYWxpZGF0ZVRpbWVvdXRPdXRPZlJhbmdlIiwiaW5pdGlhbGl6ZSIsImRyb3Bkb3duIiwiaW5pdGlhbGl6ZUZvcm0iLCJFeHRlbnNpb25zIiwiZ2V0RHJvcGRvd25TZXR0aW5nc1dpdGhvdXRFbXB0eSIsImNiQmVmb3JlU2VuZEZvcm0iLCJzZXR0aW5ncyIsInJlc3VsdCIsImRhdGEiLCJmb3JtIiwiY2JBZnRlclNlbmRGb3JtIiwiRm9ybSIsInVybCIsImdsb2JhbFJvb3RVcmwiLCJkb2N1bWVudCIsInJlYWR5Il0sIm1hcHBpbmdzIjoiOztBQUFBOzs7Ozs7O0FBT0E7QUFFQSxJQUFNQSxtQkFBbUIsR0FBRztBQUMzQkMsRUFBQUEsUUFBUSxFQUFFQyxDQUFDLENBQUMsc0JBQUQsQ0FEZ0I7QUFFM0JDLEVBQUFBLGlCQUFpQixFQUFFRCxDQUFDLENBQUMsV0FBRCxDQUZPO0FBRzNCRSxFQUFBQSx5QkFBeUIsRUFBRUYsQ0FBQyxDQUFDLHlDQUFELENBSEQ7QUFJM0JHLEVBQUFBLGFBQWEsRUFBRTtBQUNkQyxJQUFBQSxTQUFTLEVBQUU7QUFDVkMsTUFBQUEsVUFBVSxFQUFFLFdBREY7QUFFVkMsTUFBQUEsS0FBSyxFQUFFLENBQ047QUFDQ0MsUUFBQUEsSUFBSSxFQUFFLE9BRFA7QUFFQ0MsUUFBQUEsTUFBTSxFQUFFQyxlQUFlLENBQUNDO0FBRnpCLE9BRE07QUFGRyxLQURHO0FBVWRDLElBQUFBLE9BQU8sRUFBRTtBQUNSTixNQUFBQSxVQUFVLEVBQUUsU0FESjtBQUVSQyxNQUFBQSxLQUFLLEVBQUUsQ0FDTjtBQUNDQyxRQUFBQSxJQUFJLEVBQUUsaUJBRFA7QUFFQ0MsUUFBQUEsTUFBTSxFQUFFQyxlQUFlLENBQUNHO0FBRnpCLE9BRE07QUFGQztBQVZLLEdBSlk7QUF3QjNCQyxFQUFBQSxVQXhCMkI7QUFBQSwwQkF3QmQ7QUFDWmYsTUFBQUEsbUJBQW1CLENBQUNHLGlCQUFwQixDQUFzQ2EsUUFBdEM7QUFDQWhCLE1BQUFBLG1CQUFtQixDQUFDaUIsY0FBcEI7QUFDQWpCLE1BQUFBLG1CQUFtQixDQUFDSSx5QkFBcEIsQ0FBOENZLFFBQTlDLENBQXVERSxVQUFVLENBQUNDLCtCQUFYLEVBQXZEO0FBQ0E7O0FBNUIwQjtBQUFBO0FBNkIzQkMsRUFBQUEsZ0JBN0IyQjtBQUFBLDhCQTZCVkMsUUE3QlUsRUE2QkE7QUFDMUIsVUFBTUMsTUFBTSxHQUFHRCxRQUFmO0FBQ0FDLE1BQUFBLE1BQU0sQ0FBQ0MsSUFBUCxHQUFjdkIsbUJBQW1CLENBQUNDLFFBQXBCLENBQTZCdUIsSUFBN0IsQ0FBa0MsWUFBbEMsQ0FBZDtBQUNBLGFBQU9GLE1BQVA7QUFDQTs7QUFqQzBCO0FBQUE7QUFrQzNCRyxFQUFBQSxlQWxDMkI7QUFBQSwrQkFrQ1QsQ0FFakI7O0FBcEMwQjtBQUFBO0FBcUMzQlIsRUFBQUEsY0FyQzJCO0FBQUEsOEJBcUNWO0FBQ2hCUyxNQUFBQSxJQUFJLENBQUN6QixRQUFMLEdBQWdCRCxtQkFBbUIsQ0FBQ0MsUUFBcEM7QUFDQXlCLE1BQUFBLElBQUksQ0FBQ0MsR0FBTCxhQUFjQyxhQUFkO0FBQ0FGLE1BQUFBLElBQUksQ0FBQ3JCLGFBQUwsR0FBcUJMLG1CQUFtQixDQUFDSyxhQUF6QztBQUNBcUIsTUFBQUEsSUFBSSxDQUFDTixnQkFBTCxHQUF3QnBCLG1CQUFtQixDQUFDb0IsZ0JBQTVDO0FBQ0FNLE1BQUFBLElBQUksQ0FBQ0QsZUFBTCxHQUF1QnpCLG1CQUFtQixDQUFDeUIsZUFBM0M7QUFDQUMsTUFBQUEsSUFBSSxDQUFDWCxVQUFMO0FBQ0E7O0FBNUMwQjtBQUFBO0FBQUEsQ0FBNUI7QUErQ0FiLENBQUMsQ0FBQzJCLFFBQUQsQ0FBRCxDQUFZQyxLQUFaLENBQWtCLFlBQU07QUFDdkI5QixFQUFBQSxtQkFBbUIsQ0FBQ2UsVUFBcEI7QUFDQSxDQUZEIiwic291cmNlc0NvbnRlbnQiOlsiLypcbiAqIENvcHlyaWdodCDCqSBNSUtPIExMQyAtIEFsbCBSaWdodHMgUmVzZXJ2ZWRcbiAqIFVuYXV0aG9yaXplZCBjb3B5aW5nIG9mIHRoaXMgZmlsZSwgdmlhIGFueSBtZWRpdW0gaXMgc3RyaWN0bHkgcHJvaGliaXRlZFxuICogUHJvcHJpZXRhcnkgYW5kIGNvbmZpZGVudGlhbFxuICogV3JpdHRlbiBieSBBbGV4ZXkgUG9ydG5vdiwgOCAyMDIwXG4gKi9cblxuLyogZ2xvYmFsIGdsb2JhbFJvb3RVcmwsZ2xvYmFsVHJhbnNsYXRlLCBFeHRlbnNpb25zLCBGb3JtICovXG5cbmNvbnN0IGluY29taW5nUm91dGVNb2RpZnkgPSB7XG5cdCRmb3JtT2JqOiAkKCcjaW5jb21pbmctcm91dGUtZm9ybScpLFxuXHQkcHJvdmlkZXJEcm9wRG93bjogJCgnI3Byb3ZpZGVyJyksXG5cdCRmb3J3YXJkaW5nU2VsZWN0RHJvcGRvd246ICQoJyNpbmNvbWluZy1yb3V0ZS1mb3JtIC5mb3J3YXJkaW5nLXNlbGVjdCcpLFxuXHR2YWxpZGF0ZVJ1bGVzOiB7XG5cdFx0ZXh0ZW5zaW9uOiB7XG5cdFx0XHRpZGVudGlmaWVyOiAnZXh0ZW5zaW9uJyxcblx0XHRcdHJ1bGVzOiBbXG5cdFx0XHRcdHtcblx0XHRcdFx0XHR0eXBlOiAnZW1wdHknLFxuXHRcdFx0XHRcdHByb21wdDogZ2xvYmFsVHJhbnNsYXRlLmlyX1ZhbGlkYXRlRm9yd2FyZGluZ1RvQmVGaWxsZWQsXG5cdFx0XHRcdH0sXG5cdFx0XHRdLFxuXHRcdH0sXG5cdFx0dGltZW91dDoge1xuXHRcdFx0aWRlbnRpZmllcjogJ3RpbWVvdXQnLFxuXHRcdFx0cnVsZXM6IFtcblx0XHRcdFx0e1xuXHRcdFx0XHRcdHR5cGU6ICdpbnRlZ2VyWzMuLjMwMF0nLFxuXHRcdFx0XHRcdHByb21wdDogZ2xvYmFsVHJhbnNsYXRlLmlyX1ZhbGlkYXRlVGltZW91dE91dE9mUmFuZ2UsXG5cdFx0XHRcdH0sXG5cdFx0XHRdLFxuXHRcdH0sXG5cdH0sXG5cdGluaXRpYWxpemUoKSB7XG5cdFx0aW5jb21pbmdSb3V0ZU1vZGlmeS4kcHJvdmlkZXJEcm9wRG93bi5kcm9wZG93bigpO1xuXHRcdGluY29taW5nUm91dGVNb2RpZnkuaW5pdGlhbGl6ZUZvcm0oKTtcblx0XHRpbmNvbWluZ1JvdXRlTW9kaWZ5LiRmb3J3YXJkaW5nU2VsZWN0RHJvcGRvd24uZHJvcGRvd24oRXh0ZW5zaW9ucy5nZXREcm9wZG93blNldHRpbmdzV2l0aG91dEVtcHR5KCkpO1xuXHR9LFxuXHRjYkJlZm9yZVNlbmRGb3JtKHNldHRpbmdzKSB7XG5cdFx0Y29uc3QgcmVzdWx0ID0gc2V0dGluZ3M7XG5cdFx0cmVzdWx0LmRhdGEgPSBpbmNvbWluZ1JvdXRlTW9kaWZ5LiRmb3JtT2JqLmZvcm0oJ2dldCB2YWx1ZXMnKTtcblx0XHRyZXR1cm4gcmVzdWx0O1xuXHR9LFxuXHRjYkFmdGVyU2VuZEZvcm0oKSB7XG5cblx0fSxcblx0aW5pdGlhbGl6ZUZvcm0oKSB7XG5cdFx0Rm9ybS4kZm9ybU9iaiA9IGluY29taW5nUm91dGVNb2RpZnkuJGZvcm1PYmo7XG5cdFx0Rm9ybS51cmwgPSBgJHtnbG9iYWxSb290VXJsfWluY29taW5nLXJvdXRlcy9zYXZlYDtcblx0XHRGb3JtLnZhbGlkYXRlUnVsZXMgPSBpbmNvbWluZ1JvdXRlTW9kaWZ5LnZhbGlkYXRlUnVsZXM7XG5cdFx0Rm9ybS5jYkJlZm9yZVNlbmRGb3JtID0gaW5jb21pbmdSb3V0ZU1vZGlmeS5jYkJlZm9yZVNlbmRGb3JtO1xuXHRcdEZvcm0uY2JBZnRlclNlbmRGb3JtID0gaW5jb21pbmdSb3V0ZU1vZGlmeS5jYkFmdGVyU2VuZEZvcm07XG5cdFx0Rm9ybS5pbml0aWFsaXplKCk7XG5cdH0sXG59O1xuXG4kKGRvY3VtZW50KS5yZWFkeSgoKSA9PiB7XG5cdGluY29taW5nUm91dGVNb2RpZnkuaW5pdGlhbGl6ZSgpO1xufSk7XG4iXX0=