import { HttpClient, HttpHeaders, HttpParams } from '@angular/common/http';
import { Inject, Injectable } from '@angular/core';
import { ToastController } from 'ionic-angular';
import { Observable } from 'rxjs';
import * as _ from 'lodash';
import { GrowlService } from './growl.service';
import { TranslateService } from '@ngx-translate/core';
import { XhrOptions, XhrService } from './xhr.service';

declare let $: JQueryStatic;

export const stdXhrOpts = {noThrow: true, throwVal: false};

export class ContextCrudServ {
  constructor(public url: string, public crud: CrudService) {
  }

  setDefaultOpts(opts) {
    _.defaults(opts, {noThrow: true, displayError: true, noError: true});
  }

  combineUrlData(data = '') {
    if (!data) {
      return this.url;
    } else {
      return this.url + '/' + data;
    }
  }

  async op(op: string, urlData, data?, opts?: XhrOptions) {
    opts = opts || {};
    this.setDefaultOpts(opts);

    console.log('comb', this.combineUrlData(urlData));
    let result;
    switch (op) {
      case 'get':
        result = await this.crud.get(this.combineUrlData(urlData), opts);
        break;
      case 'post':
        result = await this.crud.post(this.combineUrlData(urlData), data, opts);
        break;
      case 'patch':
        result = await this.crud.patch(this.combineUrlData(urlData), data, opts);
        break;
      case 'put':
        result = await this.crud.put(this.combineUrlData(urlData), data, opts);
        break;
      case 'delete':
        result = await this.crud.delete(this.combineUrlData(urlData), opts);
        break;
      default:
        return false;
    }
    return result;
  }

  async get(urlData = '', opts?: XhrOptions) {
    return await this.op('get', urlData, null, opts);
  }

  async post(urlData = '', postData = {}, opts?: XhrOptions) {
    return await this.op('post', urlData, postData, opts);

  }
  async patch(urlData = '', postData = {}, opts?: XhrOptions) {
    return await this.op('patch', urlData, postData, opts);

  }
  async delete(urlData = '', opts?: XhrOptions) {
    return await this.op('delete', urlData, null, opts);

  }


}

@Injectable()
export class CrudService {
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
      this.url = '.';
    } else {
      this.url = 'http://myapp.localhost';
    }
  }

  setUrl(url: string): ContextCrudServ {
    console.log('url', this.url + '/' + url);
    return new ContextCrudServ(this.url + '/' + url, this);
  }

  completeUrl(url) {
    return this.url + '/' + url;
  }

  getOptions(extra = {}) {
    return _.defaults(extra, {
        headers: new HttpHeaders(
          {
            'Content-Type': 'text/plain; charset=UTF-8'
          }
        ),
        withCredentials: true
      }
    )
  }

  async post(url, data, options?: XhrOptions) {


    let body = JSON.stringify(data);

    // console.log('myxhr...');

    let res = await this.process(this.http.post(
      url,
      body,
      this.getOptions()), options);

    return res.result ? res.data : null;
  }

  async patch(url, data, options?: XhrOptions) {

    let body = JSON.stringify(data);

    // console.log('myxhr...');

    let res = await this.process(this.http.patch(
      url,
      body,
      this.getOptions()), options);

    return res.result ? res.data : null;
  }

  async put(url, data, options?: XhrOptions) {

    let body = JSON.stringify(data);

    // console.log('myxhr...');

    let res = await this.process(this.http.put(
      url,
      body,
      this.getOptions()), options);

    return res.result ? res.data : null;
  }

  async delete(url, options?: XhrOptions) {

    // console.log('myxhr...');

    let res = await this.process(this.http.delete(
      url,
      this.getOptions()), options);

    return res.result ? true : null;
  }

  async get(url: string, options?: XhrOptions) {
    // console.log('get', context, op, data, options);
    // let params = new HttpParams(
    //   {
    //   context,
    //   op,
    //   data: JSON.stringify(data)
    // } as HttpParamsOptions
    // );

    let headers = new HttpHeaders(
      {
        'Content-Type': 'text/plain; charset=UTF-8'
      }
    );
    // console.log(params);
    // console.log(params.toString());

    let xhr_options = {headers, observe: 'body', withCredentials: true, responseType: 'json'} as any;
    // console.log('url', url);
    let res = await this.process(this.http.get(url, xhr_options), options);

    return res.result ? res.data || res.liste : null;
  }


  process(http_observable_result, options: XhrOptions = {}) {

    let to_ret = http_observable_result
      
      .map((resp: Response) => {
        console.log('reponse.................');
        return this.extractData(resp);
      })
      .catch((data) => {
          console.log('cccccccccatch..........', data);
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


    console.log('returning to promise of ', to_ret);
    return to_ret.toPromise();


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
