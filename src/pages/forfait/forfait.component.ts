import { Component, OnInit, OnDestroy } from '@angular/core';
import { NavController } from 'ionic-angular';
import { Subscription } from 'rxjs';
import { NgRedux, select } from '@angular-redux/store';
import { FmService } from '../../services/fmService';
import { MatchsService } from '../../services/matchs.service';
import { IAppState } from '../../store/store';
import * as _ from 'lodash';
import * as R from 'ramda';

@Component(
  {
    selector: '',
    templateUrl: './forfait.component.html'
  }
)
export class ForfaitComponent implements OnInit, OnDestroy {

  public subs: Subscription[] = [];
  public initializing = true;
  public erreurs = [];
  public idMatch;
  public forfait: boolean = false;

  @select(['mesMatchs', 'idMatch']) $idMatch;
  constructor(
    public store: NgRedux<IAppState>,
    public fmServ: FmService,
    public navCtrl: NavController
  ) {

  }

  ngOnInit() {
    this.subs.push(
      this.$idMatch.subscribe(id => this.idMatch = id)
    );
    this.init();

  }

  ngOnDestroy() {
    this.subs.forEach(s => s.unsubscribe());
  }

  async init(proceder = false) {
    this.initializing = true;
    this.erreurs = [];

    let result = await this.fmServ.verifierForfait(this.idMatch, proceder);
    this.initializing = false;
    if (result) {
      if (proceder && result.erreurs.length === 0) {
        this.fmServ.toast('Victoire par forfait enregistrée');
        this.navCtrl.pop();
        return;
      }
      this.erreurs = result.erreurs;
      this.forfait = R.propOr(false, 'deja_forfait', result);
    } else {
      this.erreurs = ['Impossible de vérifier la possibilité de forfait'];
      this.forfait = null;
    }
  }

  async retirerForfait() {
    let succes = await this.fmServ.retirerForfait(this.idMatch);
    if (succes) {
      this.fmServ.toast('Victoire par forfait retirée');
      this.navCtrl.pop();
    }
  }

}
