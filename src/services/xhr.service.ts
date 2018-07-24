import { HttpClient, HttpHeaders, HttpParams } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { ToastController } from 'ionic-angular';
import { Observable } from 'rxjs';
import * as _ from 'lodash';
import { GrowlService } from './growl.service';
import { TranslateService } from '@ngx-translate/core';

declare let $: JQueryStatic;

export interface XhrOptions {
  overlay?: boolean | JQueryStatic | JQuery;
  observableResult?: boolean;
  withCredentials?: boolean;
  noThrow?: boolean;
  throwVal?: any;
  onStart?: () => {};
  onDone?: () => {};
  context?: any;
  tr?: TranslateService;
  noError?: boolean;
  displayError?: boolean;
}

export const stdXhrOpts = {noThrow: true, throwVal: false};

export class ContextXhrServ {
  constructor(public xhr: XhrService, public tr: TranslateService, public growl: GrowlService, public context: string) {
  }

  async get(op: string, data: {}, opts?: XhrOptions) {
    opts = opts || {};
    _.defaults(opts, {overlay: true, noThrow: false});
    try {
      if (opts && opts.onStart) {
        opts.onStart.call(opts.context || this.xhr);
      }
      let result = await this.xhr.get(this.context, op, data, opts);
      if (opts && opts.onDone) {
        opts.onDone.call(opts.context || this.xhr);
      }
      if (!result.result) {
        this.growl.xhr_error(result, opts.tr || this.tr);
      }
      return result;
    } catch (err) {

      if (opts && opts.onDone) {
        opts.onDone.call(opts.context || this.xhr);
      }
      this.growl.xhr_error(err, opts.tr || this.tr);
      if (!opts.noThrow) {
        if (opts.throwVal === undefined) {
          throw err;
        }
        throw opts.throwVal;
      }
      return opts.throwVal === undefined ? err : opts.throwVal;
    }

  }

  async post(op: string, data: {}, opts?: XhrOptions) {
    opts = opts || {};
    _.defaults(opts, {overlay: true, noThrow: false});
    try {
      if (opts && opts.onStart) {
        opts.onStart.call(opts.context || this.xhr);
      }

      let result = await this.xhr.post(this.context, op, data, opts);
      if (opts && opts.onDone) {
        opts.onDone.call(opts.context || this.xhr);
      }
      if (!result.result) {
        this.growl.xhr_error(result, opts.tr || this.tr);
      }
      return result;
    } catch (err) {
      if (opts && opts.onDone) {
        opts.onDone.call(opts.context || this.xhr);
      }

      this.growl.xhr_error(err, opts.tr || this.tr);
      if (!opts.noThrow) {
        if (opts.throwVal === undefined) {
          throw err;
        }
        throw opts.throwVal;
      }
      return opts.throwVal === undefined ? err : opts.throwVal;
    }

  }
}

@Injectable()
export class XhrService {
  public local: boolean;

  public url;
  public urlPHP;

  constructor(
    private http: HttpClient,
    private Growl: GrowlService,
    public toastCtrl: ToastController
  ) {
    this.url = `http://${location.host}/PHP/process_request2.php`;
    this.local = (window.location.hostname.indexOf('localhost') > -1);
    this.urlPHP = this.local ? `http://${location.host}/PHP` : './PHP';
    if (!this.local) {
      this.url = 'PHP/process_request2.php';
    } else {
      this.url = 'http://myapp.localhost/PHP/process_request2.php';
    }


  }

  setContext(context: string, tr: TranslateService) {
    return new ContextXhrServ(this, tr, this.Growl, context);
  }

  post(context: string, op: string, data: {}, options?: XhrOptions) {
    let headers = new HttpHeaders(
      {
        'Content-Type': 'text/plain; charset=UTF-8'
      }
    );
    let xhr_options = {headers, withCredentials: true};

    let body = JSON.stringify({context, op, data});

    // console.log('myxhr...');

    return this.process(this.http.post(
      this.url,
      body,
      xhr_options), options);

  }

  get(context: string, op: string, data = {}, options?: XhrOptions) {
    // console.log('get', context, op, data, options);
    let params = new HttpParams(
    //   {
    //   context,
    //   op,
    //   data: JSON.stringify(data)
    // } as HttpParamsOptions
    );
    params = params.set('context', context);
    params = params.set('op', op);
    params = params.set('data', JSON.stringify(data));
    let headers = new HttpHeaders(
      {
        'Content-Type': 'text/plain; charset=UTF-8'
      }
    );
    // console.log(params);
    // console.log(params.toString());

    let xhr_options = {headers, observe: 'body', params, withCredentials: true, responseType: 'json'} as any;
    let url = this.url + '?' + params.toString();
    // console.log('url', url);
    return this.process(this.http.get(this.url, xhr_options), options);
  }



  process(http_observable_result, options: XhrOptions = {}) {
    let overlayTarget: any;
    if (options.overlay) {
      if (_.isObject(options.overlay)) {
        overlayTarget = options.overlay;
      } else {
        overlayTarget = $('body');
      }
      overlayTarget.plainOverlay('show');
    }
    let to_ret = http_observable_result
      .map((resp: Response) => {
        if (overlayTarget) {
          overlayTarget.plainOverlay('hide');
        }
        return this.extractData(resp);
      })
      .catch((data) => {
          if (overlayTarget) {
            overlayTarget.plainOverlay('hide');
          }
          return Observable.create((subscriber) => {
            if (options.noError) {
              subscriber.next(data);
            } else {
              subscriber.error(data);
            }

            if (options.displayError) {
              let toast = this.toastCtrl.create({
                message: data.msg + (data.ref ? `(${data.ref})` : ''),
                duration: 5000
              });
              toast.present();
            }

            subscriber.complete();
          });
        }
      );
    if (options.observableResult) {
      return to_ret;
    }
    if (!options.noThrow) {
      return to_ret.toPromise();
    }

    return to_ret.toPromise()
      .then(
        data => {
          return data;
        }
        ,
        data => {
          return (options.throwVal !== undefined ? options.throwVal : data);
        }
      );

  }

  extractData(res: any) {
    if (res.status < 200 || res.status >= 300) {
      throw {msg: 'erreur_comm_serveur', ref: res.status, result: 0};
    }
    let body = res;
    if (body.result === 0) {
      throw body;
    }
    return body;
  }

}
