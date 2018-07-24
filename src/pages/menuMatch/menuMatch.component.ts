import { Component, OnInit, OnDestroy } from '@angular/core';
import { AlertController, NavController } from 'ionic-angular';
import { Subscription } from 'rxjs';
import { NgRedux, select } from '@angular-redux/store';
import { FmService } from '../../services/fmService';
import { MatchRecord } from '../../store/matchs';
import { IAppState } from '../../store/store';
import * as R from 'ramda';
import { AfficherJoueursComponent } from '../afficherJoueurs/afficherJoueurs.component';
import { ChoixEquipeComponent } from '../choixEquipe/choixEquipe.component';
import { CollecterStatsComponent } from '../collecterStats/collecterStats.component';
import { ChoisirPeriodeComponent } from '../feuilleMatch/choixPeriode/choisirPeriode.component';
import { ForfaitComponent } from '../forfait/forfait.component';
import {substitute} from '../../lib/substitute'

@Component(
  {
    templateUrl: './menuMatch.component.html'
  }
)
export class MenuMatchComponent implements OnInit, OnDestroy {

  public subs: Subscription[] = [];
  public isForfait: boolean;
  public isResults: boolean;
  public infoMatch: MatchRecord;
  public stats_ok: boolean;
  public mode = 'menu';
  public editable = false;

  @select(['mesMatchs', 'idMatch']) $idMatch;
  @select(['mesMatchs', 'liste']) $liste;

  constructor(
    public store: NgRedux<IAppState>,
    public navCtrl: NavController,
    public alertCtrl: AlertController,
    public fmServ: FmService
  ) {

  }

  ngOnInit() {
    this.subs.push(
      this.$liste.subscribe(liste => {
        this.infoMatch = liste.get(this.store.getState().mesMatchs.get('idMatch'));
        this.isForfait = this.infoMatch.get('forfait1') || this.infoMatch.get('forfait2');
        this.isResults = this.infoMatch.get('pts1') !== null && this.infoMatch.get('pts2') !== null;
        this.stats_ok = this.infoMatch.get('sj_ok1') === 1 && this.infoMatch.get('sj_ok2') === 1;
        this.editable = this.infoMatch.get('sj_ok1') === 0 && this.infoMatch.get('sj_ok2') === 0;
      })
    );

  }

  ngOnDestroy() {
    this.subs.forEach(s => s.unsubscribe());
  }

  choixEquipeJoueurs() {
    this.navCtrl.push(ChoixEquipeComponent);
  }

  afficherJoueurs() {
    this.navCtrl.push(AfficherJoueursComponent);
  }

  feuilleMatchChoixPeriode() {
    console.log('editable menu match', this.editable);
    this.navCtrl.push(ChoisirPeriodeComponent, {editable: this.editable})
  }

  forfait() {
    this.navCtrl.push(ForfaitComponent);
  }

  async effacerResultats() {
    let alert = this.alertCtrl.create({
      message: 'Les statistiques des joueurs liées à ce match seront aussi effacées. Procéder?',
      buttons: [
        {
          text: 'Non'
        },
        {
          text: 'OK',
          handler: () => {
            let succes = this.fmServ.effacerResultats(this.store.getState().mesMatchs.get('idMatch'));
            if (succes) {
              this.fmServ.toast('Résultats du match effacés')
            }
          }
        }
      ]
    });
    alert.present();
  }

  test() {
    console.log(substitute('toto {nb} slajflks', {nb: 334}));
  }

  collecterStats(force = false) {
    if (this.stats_ok && !force) {
      this.mode = 'choix_collecter_stats';
      return;
    }

    this.navCtrl.push(CollecterStatsComponent);
    this.mode = 'menu';
  }

  effacerStats() {
    let alert = this.alertCtrl.create({
      title: 'Effacement des statistiques',
      message: 'Vous devez effacer les statistiques du match avant de modifier la feuille de match. Le résultat du match sera conservé. Une fois la feuille de match modifiée, colligez à nouveau les statistiques.',
      buttons: [
        {
          text: 'Annuler'
        },
        {
          text: 'Procéder',
          handler: () => {
            this.fmServ.effacerResultats(this.store.getState().mesMatchs.get('idMatch'), true);
            this.mode = 'menu'
          }
        }
      ]
    });
    alert.present();
  }

}
