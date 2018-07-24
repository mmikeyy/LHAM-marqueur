/**
 * Created by micra_000 on 2016-05-12.
 */
import { Injectable } from '@angular/core';
import { Message } from 'primeng/api';
import { TranslateService } from '@ngx-translate/core';

declare function require(str: string): any;

declare let $: JQueryStatic;
import * as _ from 'lodash';
import * as R from 'ramda';

@Injectable()
export class GrowlService {
  static nextId = 0;
  public msgs: Message[] = [];
  public growlServiceId;

  constructor(public tr: TranslateService) {
    this.growlServiceId = GrowlService.nextId++;
  }

  info(title: string, info?: string) {
    this.msg('info', title, info);
  }

  warning(title: string, info?: string) {
    this.msg('warn', title, info);
  }

  error(title: string, info?: string) {
    this.msg('error', title, info);
  }

  msg(severity: string, title: string, info?: string) {
    this.msgs.push({severity, summary: title, detail: info || ''});
    this.msgs = this.msgs.slice(0);
  }

  translate_data(data: { msg?: string, ref?: string }, tr: TranslateService): { summary: string, detail?: string } | boolean {
    let res: { summary: string, detail?: string } | boolean = false;
    if (!data.msg) {
      return res;
    }
    let originalMsg = data.msg;
    let msg = originalMsg;
    if (tr) {
      msg = tr.instant(originalMsg);
      // console.log('traduction de ', original_msg, '=>', msg);
    }
    if (msg === originalMsg) {
      // console.log('pas de changement');
      msg = this.tr.instant(originalMsg);
      // console.log('traduction défaut = ', original_msg, '=>', msg);
    }
    res = {summary: msg};
    if (data.ref) {
      res['detail'] = data.ref;
    }
    return res;
  }

  xhr_error(data: Object | string, tr?: TranslateService) {
    // let toType = (obj) => {
    //   return ({}).toString.call(obj).match(/\s([a-zA-Z]+)/)[1].toLowerCase();
    // };

    if (_.isString(data)) {
      console.log('data is string');
      data = {msg: data};
    } else if (data instanceof SyntaxError) {
      console.error(data['message']);
      data = {msg: 'SyntaxError', ref: data.message.substr(0, 100) };
    } else if (!R.is(Object, data)) {
      data = {msg: 'erreur'};
    }

    let translated = this.translate_data(data as Object, tr);
    if (translated) {
      let msg = _.extend(translated, {severity: 'error'});
      this.msgs.push(msg);
      return msg;
    }
    return false;
  }

  xhr_error_msg(data: Object, array: Array<{}>, tr?: TranslateService) {
    let msg = this.xhr_error(data, tr);
    if (msg) {
      array.push(msg);
    }
  }

  clear() {
    this.msgs.splice(0, this.msgs.length);
  }

  onClose(ev: {message: Message}) {
    if (!ev || !ev.message) {
      return;
    }
    let pos = this.msgs.indexOf(ev.message);
    if (pos > -1) {
      this.msgs.splice(pos, 1);
    }
  }
}
