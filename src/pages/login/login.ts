import { Component, OnInit, OnDestroy } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { NavController } from 'ionic-angular';
import { Subscription } from 'rxjs';
import { TranslateService } from '@ngx-translate/core';
import { NgRedux, select } from '@angular-redux/store';
import { GrowlService } from '../../services/growl.service';
import { LoginService } from '../../services/login.service';
import { IAppState } from '../../store/store';
import { SessionActions } from '../../store/session/session.actions';


@Component(
  {
    selector: '',
    templateUrl: 'login.html'
  }
)
export class Login implements OnInit, OnDestroy {

  public subs: Subscription[] = [];
  public userName: string = '';
  public pw: string = '';
  public form: FormGroup;

  @select(['session', 'user', 'id']) $idVisiteur;
  @select(['session', 'user', 'nom']) $nomVisiteur;

  constructor(
    public store: NgRedux<IAppState>,
    public trServ: TranslateService,
    public navCtrl: NavController,
    public fb: FormBuilder,
    public loginServ: LoginService,
    public sessionActions: SessionActions,
    public growl: GrowlService
  ) {

  }

  ngOnInit() {
    this.subs.push();
    this.form = this.fb.group({
      userName: ['', [Validators.minLength(4), Validators.required]],
      pw: ['', [Validators.minLength(4), Validators.required]]
    });

    this.sessionActions.ensureSalt();
  }

  ngOnDestroy() {
    this.subs.forEach(s => s.unsubscribe());
  }

  tr(tag, data = {}) {
    return this.trServ.instant(tag, data);
  }

  logout() {
    this.loginServ.logout();
  }

  login() {
    if (!this.form.valid) {
      return;
    }
    this.loginServ.login(this.form.value.userName, this.form.value.pw);
  }

}
